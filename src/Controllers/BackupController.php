<?php

declare(strict_types=1);

namespace Snawbar\Backup\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Snawbar\Backup\Support\BackgroundProcess;
use Snawbar\Backup\Support\BackupArchive;
use Snawbar\Backup\Support\BackupJob;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController
{
    public function download(): BinaryFileResponse
    {
        $directory = $this->temporaryDirectory();

        $zipFile = BackupArchive::fromConfig()->build($directory);

        return response()->download($zipFile, $this->fileName(), ['Content-Type' => 'application/zip']);
    }

    public function start(): JsonResponse
    {
        $backupJob = BackupJob::create(BackupArchive::fromConfig(), $this->fileName(), $this->owner());

        BackgroundProcess::backup($backupJob->token());

        return response()->json([
            'token' => $backupJob->token(),
        ]);
    }

    public function status(string $token): JsonResponse
    {
        $backupJob = $this->job($token);

        return response()->json($backupJob->progress($this->showsDetails()));
    }

    public function file(string $token): BinaryFileResponse
    {
        $backupJob = $this->job($token);

        abort_unless($backupJob->isReady(), 404);

        register_shutdown_function(fn () => $backupJob->delete());

        return response()->download($backupJob->zipPath(), $backupJob->fileName(), ['Content-Type' => 'application/zip']);
    }

    public function cancel(string $token): JsonResponse
    {
        $this->job($token)->cancel();

        return response()->json([
            'state' => 'cancelled',
        ]);
    }

    private function job(string $token): BackupJob
    {
        $backupJob = BackupJob::find($token, $this->owner());

        abort_if(! $backupJob instanceof BackupJob, 404);

        return $backupJob;
    }

    private function showsDetails(): bool
    {
        $showDetails = config('snawbar-backup.show_details', FALSE);

        return match (TRUE) {
            is_callable($showDetails) => (bool) call_user_func($showDetails),
            default => (bool) $showDetails,
        };
    }

    private function owner(): string
    {
        return sprintf('%s|%s', request()->getHost(), auth()->id());
    }

    private function temporaryDirectory(): string
    {
        $directory = sprintf('%s%s%s', BackupJob::root(), DIRECTORY_SEPARATOR, Str::uuid());

        File::ensureDirectoryExists($directory, 0700);

        register_shutdown_function(fn () => File::deleteDirectory($directory));

        return $directory;
    }

    private function fileName(): string
    {
        return BackupArchive::resolve(config('snawbar-backup.file_name'));
    }
}
