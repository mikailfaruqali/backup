<?php

declare(strict_types=1);

namespace Snawbar\Backup\Support;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class BackgroundProcess
{
    public static function backup(string $token): void
    {
        $command = implode(' ', array_map('escapeshellarg', [
            self::phpBinary(),
            base_path('artisan'),
            'snawbar-backup:create',
            $token,
        ]));

        $detached = match (PHP_OS_FAMILY) {
            'Windows' => sprintf('start "" /B %s > NUL 2>&1', $command),
            default => sprintf('nohup %s > /dev/null 2>&1 &', $command),
        };

        Process::fromShellCommandline($detached, base_path())->run();
    }

    private static function phpBinary(): string
    {
        $configured = config()->string('snawbar-backup.php_binary', '');

        return match (TRUE) {
            filled($configured) => $configured,
            default => (new PhpExecutableFinder)->find(FALSE) ?: 'php',
        };
    }
}
