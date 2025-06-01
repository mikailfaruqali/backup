<?php

namespace Snawbar\Backup\Http\Controllers;

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
        $sqlFile = sprintf('%s.%s', tempnam(sys_get_temp_dir(), 'sql'), '.sql');

        MySql::create()
            ->setDumpBinaryPath(config()->string('snawbar-backup.mysql_dump_path'))
            ->setHost(config('database.connections.mysql.host'))
            ->setDbName(config('database.connections.mysql.database'))
            ->setUserName(config('database.connections.mysql.username'))
            ->setPassword(config('database.connections.mysql.password'))
            ->dumpToFile($sqlFile);

        return $sqlFile;
    }

    private function createZipWithPassword(string $sqlFile): string
    {
        $zipFile = sprintf('%s.%s', tempnam(sys_get_temp_dir(), 'zip'), '.zip');

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
        }, call_user_func(config()->string('snawbar-backup.file_name')), ['Content-Type' => 'application/zip']);
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
        return call_user_func(config()->string('snawbar-backup.zip_password'));
    }
}
