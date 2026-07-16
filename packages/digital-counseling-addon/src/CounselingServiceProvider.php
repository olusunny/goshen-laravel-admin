<?php

namespace ChurchTools\DigitalCounseling;

use ChurchTools\DigitalCounseling\Console\HealthCheckCounselingCommand;
use ChurchTools\DigitalCounseling\Console\InstallCounselingCommand;
use ChurchTools\DigitalCounseling\Console\UninstallCounselingCommand;
use ChurchTools\DigitalCounseling\Console\UpdateCounselingCommand;
use ChurchTools\DigitalCounseling\Contracts\PermissionResolverContract;
use ChurchTools\DigitalCounseling\Contracts\ProtectedMediaStorageContract;
use ChurchTools\DigitalCounseling\Services\DefaultPermissionResolver;
use ChurchTools\DigitalCounseling\Services\LocalProtectedMediaStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class CounselingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/counseling.php', 'counseling');

        $this->app->bind(PermissionResolverContract::class, DefaultPermissionResolver::class);
        $this->app->bind(ProtectedMediaStorageContract::class, LocalProtectedMediaStorage::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutes();

        $this->publishes([
            __DIR__.'/../config/counseling.php' => config_path('counseling.php'),
        ], 'counseling-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'counseling-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCounselingCommand::class,
                UpdateCounselingCommand::class,
                HealthCheckCounselingCommand::class,
                UninstallCounselingCommand::class,
            ]);
        }
    }

    private function loadRoutes(): void
    {
        if (! $this->counselingEnabled()) {
            return;
        }

        if (config('counseling.features.api', true)) {
            Route::prefix(config('counseling.api_prefix', 'api/v1/counseling'))
                ->as('counseling.api.')
                ->middleware(config('counseling.middleware.api', ['api', 'auth:sanctum']))
                ->group(__DIR__.'/../routes/api.php');
        }

        Route::prefix('admin')
            ->as('counseling.admin.')
            ->middleware(['web', 'auth'])
            ->group(__DIR__.'/../routes/admin.php');
    }

    private function counselingEnabled(): bool
    {
        if (! filter_var(config('counseling.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            if (Schema::hasTable('app_settings')) {
                $enabled = DB::table('app_settings')
                    ->where('key', 'counseling_enabled')
                    ->value('value');

                if ($enabled !== null && ! filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
                    return false;
                }
            }

            if (! Schema::hasTable('addons')) {
                return true;
            }

            $status = DB::table('addons')
                ->where('package_key', 'church-tools.digital-counseling')
                ->value('status');

            if ($status === null) {
                return true;
            }

            return $status === 'active';
        } catch (Throwable) {
            return false;
        }
    }
}
