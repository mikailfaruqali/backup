<?php

namespace Snawbar\Backup;

use Illuminate\Support\ServiceProvider;

class BackupServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();
        $this->publishAssets();
    }

    private function registerRoutes()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
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
