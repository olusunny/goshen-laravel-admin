<?php

namespace App\Providers;

use App\Auth\MergedEmailUserProvider;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenReferralService;
use App\Services\MergedAccountCredentialService;
use App\Services\StripePaymentSettings;
use App\Services\TriumphantIdService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('merged_eloquent', function ($app, array $config): MergedEmailUserProvider {
            return new MergedEmailUserProvider($app['hash'], $config['model']);
        });

        try {
            app(StripePaymentSettings::class)->applyToConfig();
        } catch (Throwable) {
            //
        }

        MobileUser::created(function (MobileUser $user): void {
            $this->syncMergedAdminCredentialsFromMobile($user, created: true);

            if (Schema::hasColumn('mobile_users', 'triumphant_id')) {
                try {
                    app(TriumphantIdService::class)->assignFor($user);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            if (! Schema::hasTable('goshen_referral_codes')) {
                return;
            }

            try {
                app(GoshenReferralService::class)->ensureCodeFor($user);
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        MobileUser::updated(function (MobileUser $user): void {
            $this->syncMergedAdminCredentialsFromMobile($user);

            if (! Schema::hasColumn('mobile_users', 'triumphant_id')) {
                return;
            }

            if ($user->wasChanged('is_deleted')) {
                try {
                    if ($user->is_deleted) {
                        app(TriumphantIdService::class)->release($user);
                    } else {
                        app(TriumphantIdService::class)->assignFor($user);
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });

        User::saved(function (User $user): void {
            $credentials = app(MergedAccountCredentialService::class);
            if ($credentials->isSyncing()) {
                return;
            }

            if ($user->wasRecentlyCreated || $user->wasChanged(['email', 'password'])) {
                $credentials->syncMobileFromAdmin($user);
            }
        });
    }

    private function syncMergedAdminCredentialsFromMobile(MobileUser $user, bool $created = false): void
    {
        $credentials = app(MergedAccountCredentialService::class);
        if ($credentials->isSyncing()) {
            return;
        }

        if (! $created && ! $user->wasChanged(['email', 'password', 'is_verified', 'email_verified_at', 'is_blocked', 'is_deleted'])) {
            return;
        }

        $credentials->mergeForMobile($user);
    }
}
