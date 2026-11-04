<?php

declare(strict_types=1);

namespace Snawbar\Backup\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Snawbar\Backup\Dumpers\PostgreSql;
use Spatie\DbDumper\Databases\MySql;
use ZipArchive;

class BackupArchive
{
    private const COMPRESSION_LEVEL = 1;

    private const ZIP_NAME = 'snawbar-backup.zip';

    public function __construct(
        private string $driver,
        private array $connection,
        private string $password,
    ) {}

    public static function fromConfig(): self
    {
        $driver = config()->string('database.default');

        return new self(
            driver: $driver,
            connection: config()->array(sprintf('database.connections.%s', $driver)),
            password: self::resolve(config('snawbar-backup.zip_password')),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            driver: $data['driver'],
            connection: $data['connection'],
            password: $data['password'],
        );
    }

    public static function resolve(mixed $value): string
    {
        return match (TRUE) {
            is_callable($value) => (string) call_user_func($value),
            default => (string) $value,
        };
    }

    public static function zipPath(string $directory): string
    {
        return sprintf('%s%s%s', $directory, DIRECTORY_SEPARATOR, self::ZIP_NAME);
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'connection' => $this->connection,
            'password' => $this->password,
        ];
    }

    public function build(string $directory): string
    {
        $sqlFile = sprintf('%s%s%s.sql', $directory, DIRECTORY_SEPARATOR, $this->connection['database']);
        $zipFile = self::zipPath($directory);

        $this->dump(sqlFile: $sqlFile);
        $this->zip(sqlFile: $sqlFile, zipFile: $zipFile);

        File::delete($sqlFile);

        return $zipFile;
    }

    private function dump(string $sqlFile): void
    {
        $dumper = $this->dumper();
        $options = config()->array(sprintf('snawbar-backup.extra_dump_options.%s', $this->driver), []);

        foreach ($options as $option) {
            $dumper->addExtraOption($option);
        }

        $dumper->dumpToFile($sqlFile);
    }

    private function dumper(): MySql|PostgreSql
    {
        $dumper = match ($this->driver) {
            'pgsql' => PostgreSql::create()->setDumpBinaryPath(config()->string('snawbar-backup.pg_dump_path', '')),
            default => MySql::create()->setDumpBinaryPath(config()->string('snawbar-backup.mysql_dump_path', '')),
        };

        return $dumper
            ->setHost((string) $this->connection['host'])
            ->setDbName((string) $this->connection['database'])
            ->setUserName((string) $this->connection['username'])
            ->setPassword((string) ($this->connection['password'] ?? ''));
    }

    private function zip(string $sqlFile, string $zipFile): void
    {
        $zipArchive = new ZipArchive;
        $entryName = basename($sqlFile);

        throw_if($zipArchive->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE, RuntimeException::class, 'Failed to create ZIP archive');

        $zipArchive->setPassword($this->password);
        $zipArchive->addFile($sqlFile, $entryName);
        $zipArchive->setCompressionName($entryName, ZipArchive::CM_DEFLATE, self::COMPRESSION_LEVEL);
        $zipArchive->setEncryptionName($entryName, ZipArchive::EM_AES_256);

        throw_if(! $zipArchive->close(), RuntimeException::class, 'Failed to write ZIP archive');
    }
}
