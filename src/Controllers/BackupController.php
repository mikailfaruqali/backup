<?php

namespace Snawbar\Backup\Controllers;

use Spatie\DbDumper\Databases\MySql;
use ZipArchive;

class BackupController
{
    public function download()
    {
        $sqlFile = $this->createSqlDump();
        $zipFile = $this->createZipWithPassword($sqlFile);

        return $this->streamAndCleanup($zipFile, $sqlFile);
    }

    private function createSqlDump(): string
    {
        $sqlFile = sprintf('%s%ssnawbar-backup.sql', sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        $dbDumper = MySql::create()
            ->setDumpBinaryPath(config()->string('snawbar-backup.mysql_dump_path'))
            ->setHost(config('database.connections.mysql.host'))
            ->setDbName(config('database.connections.mysql.database'))
            ->setUserName(config('database.connections.mysql.username'))
            ->setPassword(config('database.connections.mysql.password'));

        foreach (config('snawbar-backup.extra_dump_options') as $option) {
            $dbDumper->addExtraOption($option);
        }

        $dbDumper->dumpToFile($sqlFile);

        return $sqlFile;
    }

    private function createZipWithPassword(string $sqlFile): string
    {
        $zipFile = sprintf('%s%ssnawbar-backup.zip', sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        $zipArchive = $this->openZipOrAbort($zipFile, $sqlFile);

        $zipArchive->setPassword($this->generatePassowrd());
        $zipArchive->addFile($sqlFile, basename($sqlFile));
        $zipArchive->setEncryptionName(basename($sqlFile), ZipArchive::EM_AES_256);
        $zipArchive->close();

        return $zipFile;
    }

    private function streamAndCleanup(string $zipFile, string $sqlFile)
    {
        return response()->streamDownload(function () use ($zipFile, $sqlFile) {
            readfile($zipFile);
            $this->cleanupFiles($sqlFile, $zipFile);
        }, $this->getFileName(), ['Content-Type' => 'application/zip']);
    }

    private function openZipOrAbort(string $zipFile, string $sqlFile): ZipArchive
    {
        $zipArchive = new ZipArchive;

        if ($zipArchive->open($zipFile, ZipArchive::CREATE) === FALSE) {
            $this->cleanupFiles($sqlFile, $zipFile);
            abort(500, 'Failed to create ZIP archive');
        }

        return $zipArchive;
    }

    private function cleanupFiles(string ...$files): void
    {
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function generatePassowrd(): string
    {
        return is_callable(config('snawbar-backup.zip_password'))
            ? call_user_func(config('snawbar-backup.zip_password'))
            : config()->string('snawbar-backup.zip_password');
    }

    private function getFileName(): string
    {
        return is_callable(config('snawbar-backup.file_name'))
            ? call_user_func(config('snawbar-backup.file_name'))
            : config()->string('snawbar-backup.file_name');
    }
}
