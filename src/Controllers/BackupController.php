<?php

declare(strict_types=1);

namespace Snawbar\Backup\Controllers;

use Illuminate\Support\Facades\File;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BackupController
{
    private ?string $tempDirectory = NULL;

    public function download(): StreamedResponse
    {
        $sqlFile = $this->createSqlDump();
        $zipFile = $this->createZipWithPassword(sqlFile: $sqlFile);

        return $this->streamAndCleanup(zipFile: $zipFile, sqlFile: $sqlFile);
    }

    public function cleanupFiles(string ...$files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if (filled($this->tempDirectory) && File::isDirectory($this->tempDirectory)) {
            File::deleteDirectory($this->tempDirectory);
        }
    }

    private function createSqlDump(): string
    {
        $sqlFile = $this->getSqlFilePath();
        $dumper = $this->configureDumper();

        $this->applyExtraOptions(dumper: $dumper);

        $dumper->dumpToFile($sqlFile);

        return $sqlFile;
    }

    private function getSqlFilePath(): string
    {
        return sprintf(
            '%s%s%s.sql',
            $this->getTempDirectory(),
            DIRECTORY_SEPARATOR,
            config()->string(sprintf('database.connections.%s.database', config()->string('database.default'))),
        );
    }

    private function configureDumper(): MySql|PostgreSql
    {
        $driver = config()->string('database.default');
        $connection = config()->array(sprintf('database.connections.%s', $driver));

        return match ($driver) {
            'pgsql' => PostgreSql::create()
                ->setDumpBinaryPath(config()->string('snawbar-backup.pg_dump_path', ''))
                ->setHost($connection['host'])
                ->setDbName($connection['database'])
                ->setUserName($connection['username'])
                ->setPassword($connection['password']),
            default => MySql::create()
                ->setDumpBinaryPath(config()->string('snawbar-backup.mysql_dump_path', ''))
                ->setHost($connection['host'])
                ->setDbName($connection['database'])
                ->setUserName($connection['username'])
                ->setPassword($connection['password']),
        };
    }

    private function applyExtraOptions(MySql|PostgreSql $dumper): void
    {
        $driver = config()->string('database.default');
        $options = config()->array(sprintf('snawbar-backup.extra_dump_options.%s', $driver), []);

        foreach ($options as $option) {
            $dumper->addExtraOption($option);
        }
    }

    private function createZipWithPassword(string $sqlFile): string
    {
        $zipFile = $this->getZipFilePath();
        $zipArchive = $this->openArchive(zipFile: $zipFile, sqlFile: $sqlFile);

        $this->configureArchive(zipArchive: $zipArchive, sqlFile: $sqlFile);
        $zipArchive->close();

        return $zipFile;
    }

    private function getZipFilePath(): string
    {
        return sprintf('%s%ssnawbar-backup.zip', $this->getTempDirectory(), DIRECTORY_SEPARATOR);
    }

    private function openArchive(string $zipFile, string $sqlFile): ZipArchive
    {
        $zipArchive = new ZipArchive;

        if ($zipArchive->open(filename: $zipFile, flags: ZipArchive::CREATE) === FALSE) {
            $this->cleanupFiles($sqlFile, $zipFile);
            abort(code: 500, message: 'Failed to create ZIP archive');
        }

        return $zipArchive;
    }

    private function configureArchive(ZipArchive $zipArchive, string $sqlFile): void
    {
        $fileName = basename($sqlFile);

        $zipArchive->setPassword($this->getPassword());
        $zipArchive->addFile($sqlFile, $fileName);
        $zipArchive->setEncryptionName($fileName, ZipArchive::EM_AES_256);
    }

    private function streamAndCleanup(string $zipFile, string $sqlFile): StreamedResponse
    {
        $controller = $this;

        return response()->streamDownload(
            callback: function () use ($zipFile, $sqlFile, $controller): void {
                readfile($zipFile);
                $controller->cleanupFiles($sqlFile, $zipFile);
            },
            name: $this->getFileName(),
            headers: ['Content-Type' => 'application/zip'],
        );
    }

    private function getTempDirectory(): string
    {
        if (filled($this->tempDirectory)) {
            return $this->tempDirectory;
        }

        $directory = sprintf(
            '%s%s%s',
            config('snawbar-backup.temp_path', storage_path('app/temp-backups')),
            DIRECTORY_SEPARATOR,
            str()->uuid(),
        );

        File::ensureDirectoryExists($directory);

        return $this->tempDirectory = $directory;
    }

    private function getPassword(): string
    {
        $password = config('snawbar-backup.zip_password');

        return is_callable($password) ? call_user_func($password) : (string) $password;
    }

    private function getFileName(): string
    {
        $fileName = config('snawbar-backup.file_name');

        return is_callable($fileName) ? call_user_func($fileName) : (string) $fileName;
    }
}
