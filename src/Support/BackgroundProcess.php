<?php

declare(strict_types=1);

namespace Snawbar\Backup\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class BackgroundProcess
{
    private const SERVER_BINARIES = [
        'php-cgi',
        'php-fpm',
    ];

    public static function backup(string $token): void
    {
        $command = [
            self::phpBinary(),
            base_path('artisan'),
            'snawbar-backup:create',
            $token,
        ];

        match (PHP_OS_FAMILY) {
            'Windows' => self::startWindows($command),
            default => self::startUnix($command),
        };
    }

    public static function detach(): bool
    {
        return function_exists('posix_setsid') && posix_setsid() !== -1;
    }

    public static function stop(int $pid, bool $isGroup): void
    {
        $command = match (TRUE) {
            PHP_OS_FAMILY === 'Windows' => sprintf('taskkill /PID %d /T /F', $pid),
            $isGroup => sprintf('kill -TERM -- -%d', $pid),
            default => sprintf('for child in $(pgrep -P %1$d); do pkill -TERM -P $child; done; pkill -TERM -P %1$d; kill -TERM %1$d', $pid),
        };

        Process::fromShellCommandline($command)->run();
    }

    private static function startWindows(array $command): void
    {
        $arguments = implode(' ', array_map('escapeshellarg', $command));

        Process::fromShellCommandline(sprintf('start "" /B %s > NUL 2>&1', $arguments), base_path())->run();
    }

    private static function startUnix(array $command): void
    {
        $arguments = implode(' ', array_map('escapeshellarg', $command));

        Process::fromShellCommandline(sprintf('nohup %s > /dev/null 2>&1 &', $arguments), base_path())->run();
    }

    private static function phpBinary(): string
    {
        $sibling = self::siblingCliBinary();

        return match (TRUE) {
            PHP_SAPI === 'cli' => PHP_BINARY,
            filled($sibling) => $sibling,
            default => (new PhpExecutableFinder)->find(FALSE) ?: 'php',
        };
    }

    private static function siblingCliBinary(): ?string
    {
        $isServerBinary = Str::startsWith(pathinfo(PHP_BINARY, PATHINFO_FILENAME), self::SERVER_BINARIES);

        $extension = match (PHP_OS_FAMILY) {
            'Windows' => '.exe',
            default => '',
        };

        $directory = dirname(PHP_BINARY);

        $candidates = [
            sprintf('%s%sphp%s', $directory, DIRECTORY_SEPARATOR, $extension),
            sprintf('%s%sbin%sphp%s', dirname($directory), DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $extension),
        ];

        return match ($isServerBinary) {
            TRUE => collect($candidates)->first(fn (string $candidate): bool => File::exists($candidate)),
            FALSE => NULL,
        };
    }
}
