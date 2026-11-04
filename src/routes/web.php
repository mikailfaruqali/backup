<?php

use Illuminate\Support\Facades\Route;
use Snawbar\Backup\Controllers\BackupController;

Route::prefix(config()->string('snawbar-backup.route', 'backup'))
    ->middleware(config()->array('snawbar-backup.middleware', ['web']))
    ->controller(BackupController::class)
    ->name('snawbar.')
    ->group(function () {
        Route::get('download', 'download')->name('backup.download');
        Route::post('start', 'start')->name('backup.start');
        Route::get('status/{token}', 'status')->name('backup.status');
        Route::get('file/{token}', 'file')->name('backup.file');
    });
