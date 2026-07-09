<?php

namespace Personal\EventInstallments;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Policies\BookingPolicy;
use Personal\EventInstallments\Policies\EventPolicy;
use Personal\EventInstallments\Policies\TicketPolicy;
use Personal\EventInstallments\Services\PaymentGatewayManager;

class EventInstallmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/event-installments.php', 'event-installments');

        $this->app->bind(PaymentGateway::class, function () {
            return $this->app->make(PaymentGatewayManager::class)->default();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/event-installments.php' => config_path('event-installments.php'),
        ], 'event-installments-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'event-installments-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'event-installments');
        $this->registerPolicies();
        $this->configureRateLimits();
        $this->loadRoutes();
    }

    private function registerPolicies(): void
    {
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
    }

    private function loadRoutes(): void
    {
        if (config('event-installments.api_routes_enabled', true)) {
            Route::prefix(config('event-installments.api_prefix'))
                ->middleware(config('event-installments.middleware.api'))
                ->group(__DIR__ . '/../routes/api.php');
        }

        if (config('event-installments.admin_routes_enabled', false)) {
            Route::prefix(config('event-installments.route_prefix'))
                ->as('event-installments.')
                ->middleware(config('event-installments.middleware.web'))
                ->group(__DIR__ . '/../routes/web.php');
        }

        Route::prefix('webhooks/event-installments')
            ->middleware(config('event-installments.middleware.webhooks'))
            ->group(__DIR__ . '/../routes/webhooks.php');
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('event-installments-api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('event-installments-webhooks', function (Request $request) {
            return Limit::perMinute(240)->by($request->ip());
        });
    }
}
