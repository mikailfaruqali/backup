<?php

declare(strict_types=1);

namespace Snawbar\Backup;

use Illuminate\Support\ServiceProvider;
use Snawbar\Backup\Commands\CreateBackup;

class BackupServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();
        $this->registerCommands();
        $this->publishAssets();
    }

    private function registerRoutes()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
    }

    private function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateBackup::class,
            ]);
        }
    }

    private function publishAssets()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/backup.php' => config_path('snawbar-backup.php'),
            ], 'snawbar-backup-config');
        }
    }
}
