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

    public function cleanupFiles(...$files)
    {
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function createSqlDump()
    {
        $sqlFile = $this->getSqlFilePath();
        $dumper = $this->configureDumper();

        $this->applyExtraOptions($dumper);

        $dumper->dumpToFile($sqlFile);

        return $sqlFile;
    }

    private function getSqlFilePath()
    {
        return sprintf(
            '%s%s%s.sql',
            sys_get_temp_dir(),
            DIRECTORY_SEPARATOR,
            config('database.connections.mysql.database')
        );
    }

    private function configureDumper()
    {
        return MySql::create()
            ->setDumpBinaryPath(config('snawbar-backup.mysql_dump_path', ''))
            ->setHost(config('database.connections.mysql.host'))
            ->setDbName(config('database.connections.mysql.database'))
            ->setUserName(config('database.connections.mysql.username'))
            ->setPassword(config('database.connections.mysql.password'));
    }

    private function applyExtraOptions($dumper)
    {
        foreach (config('snawbar-backup.extra_dump_options', []) as $option) {
            $dumper->addExtraOption($option);
        }
    }

    private function createZipWithPassword($sqlFile)
    {
        $zipFile = $this->getZipFilePath();
        $archive = $this->openArchive($zipFile, $sqlFile);

        $this->configureArchive($archive, $sqlFile);
        $archive->close();

        return $zipFile;
    }

    private function getZipFilePath()
    {
        return sprintf('%s%ssnawbar-backup.zip', sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    }

    private function openArchive($zipFile, $sqlFile)
    {
        $zipArchive = new ZipArchive;

        if ($zipArchive->open($zipFile, ZipArchive::CREATE) === FALSE) {
            $this->cleanupFiles($sqlFile, $zipFile);
            abort(500, 'Failed to create ZIP archive');
        }

        return $zipArchive;
    }

    private function configureArchive($archive, $sqlFile)
    {
        $fileName = basename($sqlFile);

        $archive->setPassword($this->getPassword());
        $archive->addFile($sqlFile, $fileName);
        $archive->setEncryptionName($fileName, ZipArchive::EM_AES_256);
    }

    private function streamAndCleanup($zipFile, $sqlFile)
    {
        $controller = $this;

        return response()->streamDownload(
            function () use ($zipFile, $sqlFile, $controller) {
                readfile($zipFile);
                $controller->cleanupFiles($sqlFile, $zipFile);
            },
            $this->getFileName(),
            ['Content-Type' => 'application/zip']
        );
    }

    private function getPassword()
    {
        $password = config('snawbar-backup.zip_password');

        return is_callable($password) ? call_user_func($password) : (string) $password;
    }

    private function getFileName()
    {
        $fileName = config('snawbar-backup.file_name');

        return is_callable($fileName) ? call_user_func($fileName) : (string) $fileName;
    }
}
