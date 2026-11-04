<?php

declare(strict_types=1);

namespace Snawbar\Backup\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupJob
{
    private const JOB_FILE = 'job.json';

    private const STATUS_FILE = 'status.json';

    public function __construct(private string $token) {}

    public static function create(BackupArchive $backupArchive, string $fileName, string $owner): self
    {
        self::cleanup();

        $backupJob = new self((string) Str::uuid());

        File::ensureDirectoryExists($backupJob->directory(), 0700);

        $backupJob->write(self::JOB_FILE, [
            'archive' => $backupArchive->toArray(),
            'file_name' => $fileName,
            'owner' => $owner,
        ]);

        $backupJob->setStatus('running');

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

    public function markReady(): void
    {
        $this->write(self::JOB_FILE, [
            'file_name' => $this->fileName(),
            'owner' => $this->payload()['owner'],
        ]);

        $this->setStatus('ready');
    }

    public function markFailed(string $message): void
    {
        $this->setStatus('failed', $message);
    }

    public function delete(): void
    {
        File::deleteDirectory($this->directory());
    }

    private static function cleanup(): void
    {
        $expiresAt = now()->subHours(config()->integer('snawbar-backup.keep_hours', 24))->getTimestamp();
        $directories = File::glob(sprintf('%s%sjob-*', self::root(), DIRECTORY_SEPARATOR), GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            when(File::lastModified($directory) < $expiresAt, fn () => File::deleteDirectory($directory));
        }
    }

    private function payload(): array
    {
        return json_decode((string) File::get($this->path(self::JOB_FILE)), TRUE);
    }

    private function setStatus(string $state, ?string $message = NULL): void
    {
        $this->write(self::STATUS_FILE, [
            'state' => $state,
            'message' => $message,
        ]);
    }

    private function write(string $file, array $data): void
    {
        File::put($this->path($file), json_encode($data, JSON_THROW_ON_ERROR), TRUE);

        File::chmod($this->path($file), 0600);
    }

    private function path(string $file): string
    {
        return sprintf('%s%s%s', $this->directory(), DIRECTORY_SEPARATOR, $file);
    }
}
