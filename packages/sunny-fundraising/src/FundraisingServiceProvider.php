<?php

namespace Sunny\Fundraising;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;
use Sunny\Fundraising\Console\HealthCheckFundraisingCommand;
use Sunny\Fundraising\Console\InstallFundraisingCommand;
use Sunny\Fundraising\Console\UninstallFundraisingCommand;
use Sunny\Fundraising\Console\UpdateFundraisingCommand;
use Sunny\Fundraising\Contracts\CampaignVisibilityContract;
use Sunny\Fundraising\Contracts\PermissionResolverContract;
use Sunny\Fundraising\Contracts\UserDisplayContract;
use Sunny\Fundraising\Contracts\WalletGatewayContract;
use Sunny\Fundraising\Services\DefaultCampaignVisibility;
use Sunny\Fundraising\Services\DefaultPermissionResolver;
use Sunny\Fundraising\Services\DefaultUserDisplay;
use Sunny\Fundraising\Services\NullWalletGateway;

class FundraisingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fundraising.php', 'fundraising');

        $this->app->bind(WalletGatewayContract::class, function () {
            $gateway = config('fundraising.wallet.gateway');

            return is_string($gateway) && class_exists($gateway)
                ? $this->app->make($gateway)
                : $this->app->make(NullWalletGateway::class);
        });

        $this->app->bind(UserDisplayContract::class, DefaultUserDisplay::class);
        $this->app->bind(PermissionResolverContract::class, DefaultPermissionResolver::class);
        $this->app->bind(CampaignVisibilityContract::class, DefaultCampaignVisibility::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fundraising');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'fundraising');
        $this->loadRoutes();

        $this->publishes([
            __DIR__.'/../config/fundraising.php' => config_path('fundraising.php'),
        ], 'fundraising-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'fundraising-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/fundraising'),
        ], 'fundraising-views');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/fundraising'),
        ], 'fundraising-translations');

        $this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/fundraising'),
        ], 'fundraising-assets');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallFundraisingCommand::class,
                UpdateFundraisingCommand::class,
                HealthCheckFundraisingCommand::class,
                UninstallFundraisingCommand::class,
            ]);
        }
    }

    private function loadRoutes(): void
    {
        if (! $this->fundraisingEnabled()) {
            return;
        }

        if (config('fundraising.features.api', true)) {
            Route::prefix(config('fundraising.api_prefix', 'api/fundraising'))
                ->as('fundraising.api.')
                ->middleware(config('fundraising.middleware.api', ['api']))
                ->group(__DIR__.'/../routes/api.php');
        }

        if (config('fundraising.features.web_pages', true)) {
            Route::prefix(config('fundraising.route_prefix', 'fundraising'))
                ->as('fundraising.')
                ->middleware(config('fundraising.middleware.web', ['web']))
                ->group(__DIR__.'/../routes/web.php');
        }

        if (config('fundraising.features.admin_pages', true)) {
            Route::prefix(config('fundraising.admin_prefix', 'admin/fundraising'))
                ->as('fundraising.admin.')
                ->middleware(config('fundraising.middleware.admin', ['web', 'auth']))
                ->group(__DIR__.'/../routes/admin.php');
        }
    }

    private function fundraisingEnabled(): bool
    {
        if (! filter_var(config('fundraising.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            if (! Schema::hasTable('addons')) {
                return true;
            }

            $status = DB::table('addons')
                ->where('package_key', 'sunny.fundraising')
                ->value('status');

            if ($status === null) {
                return true;
            }

            return $status === 'active';
        } catch (Throwable) {
            return true;
        }
    }
}
