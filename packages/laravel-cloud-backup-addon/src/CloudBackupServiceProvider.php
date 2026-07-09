<?php

namespace ChurchTools\CloudBackup;

use ChurchTools\CloudBackup\Commands\InstallCommand;
use ChurchTools\CloudBackup\Commands\RunDueBackupsCommand;
use ChurchTools\CloudBackup\Commands\RunOnDemandBackupCommand;
use ChurchTools\CloudBackup\Services\BackupManager;
use ChurchTools\CloudBackup\Services\Cloud\CloudProviderFactory;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class CloudBackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cloud-backup.php', 'cloud-backup');

        $this->app->singleton(Client::class, fn (): Client => new Client([
            'timeout' => (int) config('cloud-backup.http_timeout', 120),
            'connect_timeout' => 15,
        ]));

        $this->app->singleton(CloudProviderFactory::class);
        $this->app->singleton(BackupManager::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cloud-backup');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/cloud-backup.php' => config_path('cloud-backup.php'),
        ], 'cloud-backup-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'cloud-backup-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/cloud-backup'),
        ], 'cloud-backup-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                RunDueBackupsCommand::class,
                RunOnDemandBackupCommand::class,
            ]);
        }

        if ((bool) config('cloud-backup.auto_schedule', true)) {
            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command('cloud-backup:run-due --sync')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }
    }
}
