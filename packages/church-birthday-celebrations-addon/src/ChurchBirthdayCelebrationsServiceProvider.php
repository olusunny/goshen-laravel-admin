<?php

namespace ChurchTools\ChurchBirthdayCelebrations;

use ChurchTools\ChurchBirthdayCelebrations\Services\AddonAvailability;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayCardService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayEligibilityService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayInteractionService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayLifecycleService;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class ChurchBirthdayCelebrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/church-birthday-celebrations.php', 'church-birthday-celebrations');
        $this->app->singleton(AddonAvailability::class);
        $this->app->singleton(BirthdayEligibilityService::class);
        $this->app->singleton(BirthdayCardService::class);
        $this->app->singleton(BirthdayNotifier::class);
        $this->app->singleton(BirthdayInteractionService::class);
        $this->app->singleton(BirthdayLifecycleService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! app(AddonAvailability::class)->isActive()) {
            return;
        }

        $memberModel = config('church-birthday-celebrations.models.mobile_user');
        if (is_a($memberModel, Model::class, true)) {
            $memberModel::deleting(function (Model $member): void {
                app(BirthdayLifecycleService::class)->purgeMember($member);
            });

            $memberModel::updated(function (Model $member): void {
                if ($member->wasChanged([
                    'member_type', 'is_verified', 'is_blocked', 'is_deleted', 'triumphant_id',
                    'birthday_month', 'birthday_day', 'avatar', 'name',
                ])) {
                    app(BirthdayLifecycleService::class)->reconcileMember($member, true);
                }
            });
        }

        Route::prefix(config('church-birthday-celebrations.api_prefix'))
            ->as('church-birthday-celebrations.api.')
            ->middleware(config('church-birthday-celebrations.middleware.api'))
            ->group(__DIR__.'/../routes/api.php');

        Route::prefix(config('church-birthday-celebrations.admin_prefix'))
            ->as('church-birthday-celebrations.admin.')
            ->middleware(config('church-birthday-celebrations.middleware.admin'))
            ->group(__DIR__.'/../routes/admin.php');

        Schedule::call(fn () => app(BirthdayLifecycleService::class)->run())
            ->name('church-birthday-celebrations:lifecycle')
            ->everyMinute()
            ->withoutOverlapping();
    }
}
