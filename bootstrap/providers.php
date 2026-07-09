<?php

use App\Providers\AppServiceProvider;
use App\Providers\AddonServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use ChurchTools\CloudBackup\CloudBackupServiceProvider;
use Personal\EventInstallments\EventInstallmentsServiceProvider;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Sunny\\Fundraising\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = dirname(__DIR__).'/packages/sunny-fundraising/src/'.str_replace('\\', '/', $relativeClass).'.php';

    if (is_file($path)) {
        require $path;
    }
});

spl_autoload_register(static function (string $class): void {
    $prefix = 'ChurchTools\\CloudBackup\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = dirname(__DIR__).'/packages/laravel-cloud-backup-addon/src/'.str_replace('\\', '/', $relativeClass).'.php';

    if (is_file($path)) {
        require $path;
    }
});

spl_autoload_register(static function (string $class): void {
    $prefix = 'Personal\\EventInstallments\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = dirname(__DIR__).'/packages/event-installments/src/'.str_replace('\\', '/', $relativeClass).'.php';

    if (is_file($path)) {
        require $path;
    }
});

return [
    AppServiceProvider::class,
    AddonServiceProvider::class,
    AdminPanelProvider::class,
    CloudBackupServiceProvider::class,
    EventInstallmentsServiceProvider::class,
];
