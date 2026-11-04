<?php

declare(strict_types=1);

namespace Snawbar\Backup\Support;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use RuntimeException;
use Snawbar\Backup\Dumpers\PostgreSql;

class BackupJob
{
    private const JOB_FILE = 'job.json';

    private const STATUS_FILE = 'status.json';

    private const TABLE_FILE = 'table.txt';

    private const PROCESS_FILE = 'process.json';

    private const DELETE_ATTEMPTS = 10;

    private const DELETE_WAIT_MS = 300;

    private const DUMP_SHARE = 85;

    private const TAIL_BYTES = 1048576;

    private const START_TIMEOUT = 60;

    public function __construct(private string $token) {}

    public static function create(BackupArchive $backupArchive, string $fileName, string $owner): self
    {
        self::cleanup();

        $backupJob = new self((string) Str::uuid());

        File::ensureDirectoryExists($backupJob->directory(), 0700);

        $backupJob->write(self::JOB_FILE, [
            'archive' => $backupArchive->toArray(),
            'driver' => $backupArchive->driver(),
            'database' => $backupArchive->database(),
            'tables' => $backupArchive->tableSizes(),
            'started_at' => time(),
            'file_name' => $fileName,
            'owner' => $owner,
        ]);

        $backupJob->setProgress('queued', 0.0);

        return $backupJob;
    }

    public static function find(string $token, ?string $owner = NULL): ?self
    {
        $backupJob = new self($token);

        return match (TRUE) {
            ! Str::isUuid($token) => NULL,
            ! File::exists($backupJob->path(self::JOB_FILE)) => NULL,
            filled($owner) && $backupJob->payload()['owner'] !== $owner => NULL,
            default => $backupJob,
        };
    }

    public static function root(): string
    {
        return (string) config('snawbar-backup.temp_path', storage_path('app/temp-backups'));
    }

    public function token(): string
    {
        return $this->token;
    }

    public function directory(): string
    {
        return sprintf('%s%sjob-%s', self::root(), DIRECTORY_SEPARATOR, $this->token);
    }

    public function archive(): BackupArchive
    {
        return BackupArchive::fromArray($this->payload()['archive']);
    }

    public function fileName(): string
    {
        return $this->payload()['file_name'];
    }

    public function zipPath(): string
    {
        return BackupArchive::zipPath($this->directory());
    }

    public function status(): array
    {
        return json_decode((string) File::get($this->path(self::STATUS_FILE)), TRUE);
    }

    public function isReady(): bool
    {
        return $this->status()['state'] === 'ready' && File::exists($this->zipPath());
    }

    public function progress(bool $withDetails): array
    {
        $status = $this->status();
        $dump = $this->dumpProgress();
        $isNotStarted = $status['phase'] === 'queued' && $this->elapsed() > self::START_TIMEOUT;

        $percent = match ($status['phase']) {
            'done' => 100,
            'queued' => 0,
            'compressing' => self::DUMP_SHARE + ((100 - self::DUMP_SHARE) * $status['fraction']),
            default => self::DUMP_SHARE * $dump['fraction'],
        };

        $state = match ($isNotStarted) {
            TRUE => 'failed',
            FALSE => $status['state'],
        };

        return [
            'state' => $state,
            'phase' => $status['phase'],
            'percent' => (int) floor($percent),
            'details' => $withDetails ? $this->details($dump) : NULL,
        ];
    }

    public function setProgress(string $phase, float $fraction): void
    {
        $this->setStatus('running', $phase, $fraction);
    }

    public function markReady(): void
    {
        $payload = $this->payload();

        $this->write(self::JOB_FILE, [
            'driver' => $payload['driver'] ?? NULL,
            'database' => $payload['database'],
            'tables' => $payload['tables'],
            'started_at' => $payload['started_at'],
            'file_name' => $payload['file_name'],
            'owner' => $payload['owner'],
        ]);

        $this->setStatus('ready', 'done', 1.0);

        File::delete($this->path(self::PROCESS_FILE));
    }

    public function markFailed(string $message): void
    {
        $status = $this->status();

        $this->setStatus('failed', $status['phase'], $status['fraction'], $message);

        File::delete($this->path(self::PROCESS_FILE));
    }

    public function delete(): void
    {
        File::deleteDirectory($this->directory());
    }

    public function recordProcess(int $pid, bool $isGroup): void
    {
        $this->write(self::PROCESS_FILE, [
            'pid' => $pid,
            'group' => $isGroup,
        ]);
    }

    public function cancel(): void
    {
        $process = $this->process();
        $isRunning = $this->status()['state'] === 'running';

        if (filled($process) && $isRunning) {
            BackgroundProcess::stop($process['pid'], $process['group']);
        }

        retry(self::DELETE_ATTEMPTS, function (): void {
            File::deleteDirectory($this->directory());

            throw_if(File::isDirectory($this->directory()), RuntimeException::class, 'Backup files are still in use');
        }, self::DELETE_WAIT_MS);
    }

    private static function cleanup(): void
    {
        $expiresAt = now()->subHours(config()->integer('snawbar-backup.keep_hours', 24))->getTimestamp();
        $directories = File::glob(sprintf('%s%sjob-*', self::root(), DIRECTORY_SEPARATOR), GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            if (File::lastModified($directory) < $expiresAt) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function dumpProgress(): array
    {
        $sqlFile = BackupArchive::sqlPath($this->directory(), $this->payload()['database']);

        $size = match (File::exists($sqlFile)) {
            TRUE => File::size($sqlFile),
            FALSE => 0,
        };

        $tail = $this->tail($sqlFile, $size);
        $table = $this->currentTable($tail);

        return [
            'size' => $size,
            'table' => $table,
            'fraction' => $this->dumpFraction($table, $tail),
        ];
    }

    private function tail(string $file, int $size): string
    {
        $handle = match ($size > 0) {
            TRUE => fopen($file, 'rb'),
            FALSE => FALSE,
        };

        return match ($handle) {
            FALSE => '',
            default => $this->readTail($handle, $size),
        };
    }

    private function readTail(mixed $handle, int $size): string
    {
        fseek($handle, max(0, $size - self::TAIL_BYTES));

        $tail = (string) stream_get_contents($handle);

        fclose($handle);

        return $tail;
    }

    private function currentTable(string $tail): ?string
    {
        $table = match ($this->payload()['driver'] ?? NULL) {
            'pgsql' => PostgreSql::currentTable($tail),
            default => NULL,
        };

        if (filled($table)) {
            File::replace($this->path(self::TABLE_FILE), $table);
        }

        return $table ?? $this->lastTable();
    }

    private function lastTable(): ?string
    {
        return match (File::exists($this->path(self::TABLE_FILE))) {
            TRUE => File::get($this->path(self::TABLE_FILE)),
            FALSE => NULL,
        };
    }

    private function dumpFraction(?string $table, string $tail): float
    {
        return match ($this->payload()['driver'] ?? NULL) {
            'pgsql' => PostgreSql::dumpedFraction($this->payload()['tables'], $table, $tail),
            default => 0.0,
        };
    }

    private function details(array $dump): array
    {
        return [
            'table' => $dump['table'],
            'size' => Number::fileSize($dump['size'], precision: 1),
            'elapsed' => CarbonInterval::seconds($this->elapsed())->cascade()->forHumans(short: TRUE),
        ];
    }

    private function elapsed(): int
    {
        return time() - (int) $this->payload()['started_at'];
    }

    private function process(): ?array
    {
        return match (File::exists($this->path(self::PROCESS_FILE))) {
            TRUE => json_decode((string) File::get($this->path(self::PROCESS_FILE)), TRUE),
            FALSE => NULL,
        };
    }

    private function payload(): array
    {
        return json_decode((string) File::get($this->path(self::JOB_FILE)), TRUE);
    }

    private function setStatus(string $state, string $phase, float $fraction, ?string $message = NULL): void
    {
        $this->write(self::STATUS_FILE, [
            'state' => $state,
            'phase' => $phase,
            'fraction' => $fraction,
            'message' => $message,
        ]);
    }

    private function write(string $file, array $data): void
    {
        File::replace($this->path($file), json_encode($data, JSON_THROW_ON_ERROR), 0600);
    }

    private function path(string $file): string
    {
        return sprintf('%s%s%s', $this->directory(), DIRECTORY_SEPARATOR, $file);
    }
}
