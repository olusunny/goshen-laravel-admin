<?php

namespace ChurchTools\GoshenPrayerAttendance;

use ChurchTools\GoshenPrayerAttendance\Services\AddonAvailability;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceService;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceNotifier;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceReportService;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerSessionQrService;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerSessionAttendanceService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PrayerSessionAttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/prayer-attendance.php', 'prayer-attendance');
        $this->app->singleton(AddonAvailability::class);
        $this->app->singleton(PrayerAttendanceService::class);
        $this->app->singleton(PrayerAttendanceNotifier::class);
        $this->app->singleton(PrayerSessionQrService::class);
        $this->app->singleton(PrayerAttendanceReportService::class);
        $this->app->singleton(PrayerSessionAttendanceService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! app(AddonAvailability::class)->isActive()) {
            return;
        }

        Route::prefix(config('prayer-attendance.api_prefix'))
            ->as('prayer-attendance.api.')
            ->middleware(config('prayer-attendance.middleware.api'))
            ->group(__DIR__.'/../routes/api.php');

        Route::prefix(config('prayer-attendance.admin_prefix'))
            ->as('prayer-attendance.admin.')
            ->middleware(config('prayer-attendance.middleware.admin'))
            ->group(__DIR__.'/../routes/admin.php');
    }
}
