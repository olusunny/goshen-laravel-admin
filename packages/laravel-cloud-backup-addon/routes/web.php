<?php

use ChurchTools\CloudBackup\Http\Controllers\CloudBackupController;
use ChurchTools\CloudBackup\Http\Controllers\OAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('cloud-backup.route_middleware', ['web', 'auth']))
    ->prefix(config('cloud-backup.route_prefix', 'admin/cloud-backups'))
    ->name('cloud-backup.')
    ->group(function (): void {
        Route::get('/', [CloudBackupController::class, 'index'])->name('index');
        Route::post('/oauth-settings', [CloudBackupController::class, 'storeOAuthSettings'])->name('oauth-settings.store');
        Route::post('/oauth-settings/test', [CloudBackupController::class, 'testOAuthSettings'])->name('oauth-settings.test');
        Route::post('/oauth-settings/{provider}/reset', [CloudBackupController::class, 'resetOAuthSettings'])->name('oauth-settings.reset');
        Route::post('/schedules', [CloudBackupController::class, 'storeSchedule'])->name('schedules.store');
        Route::post('/backups/run', [CloudBackupController::class, 'runOnDemand'])->name('backups.run');
        Route::get('/runs/{run}/progress', [CloudBackupController::class, 'runProgress'])->name('runs.progress');
        Route::delete('/runs', [CloudBackupController::class, 'destroyRuns'])->name('runs.destroy-bulk');
        Route::delete('/runs/{run}', [CloudBackupController::class, 'destroyRun'])->name('runs.destroy');
        Route::post('/schedules/{schedule}/run', [CloudBackupController::class, 'runNow'])->name('schedules.run');
        Route::delete('/schedules/{schedule}', [CloudBackupController::class, 'destroySchedule'])->name('schedules.destroy');
        Route::post('/connections/{connection}/test', [CloudBackupController::class, 'testConnection'])->name('connections.test');
        Route::delete('/connections/{connection}', [CloudBackupController::class, 'destroyConnection'])->name('connections.destroy');
        Route::post('/oauth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
        Route::get('/oauth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
    });
