<?php

declare(strict_types=1);

namespace Snawbar\Backup\Commands;

use Illuminate\Console\Command;
use Snawbar\Backup\Support\BackgroundProcess;
use Snawbar\Backup\Support\BackupJob;
use Throwable;

class CreateBackup extends Command
{
    protected $signature = 'snawbar-backup:create {token}';

    protected $description = 'Build a password protected backup ZIP for a download started from the browser';

    public function handle(): int
    {
        $backupJob = BackupJob::find((string) $this->argument('token'));

        return match ($backupJob) {
            NULL => self::FAILURE,
            default => $this->build($backupJob),
        };
    }

    private function build(BackupJob $backupJob): int
    {
        try {
            $backupJob->recordProcess(getmypid(), BackgroundProcess::detach());

            $backupJob->archive()->build($backupJob->directory(), $backupJob->setProgress(...));

            $backupJob->markReady();

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $backupJob->markFailed($throwable->getMessage());

            report($throwable);

            return self::FAILURE;
        }
    }
}
