<?php

namespace App\Providers;

use App\Auth\MergedEmailUserProvider;
use App\Models\Donation;
use App\Models\DynamicFormSubmission;
use App\Models\GoshenVoucherUsage;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenReferralService;
use App\Services\GoshenTransactionEntrySyncService;
use App\Services\GoshenWalletService;
use App\Services\LinkedMobileAccountService;
use App\Services\MergedAccountCredentialService;
use App\Services\StripePaymentSettings;
use App\Services\TriumphantIdService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Personal\EventInstallments\Models\PaymentTransaction;
use Sunny\Fundraising\Models\CampaignContribution;
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

            if (Schema::hasTable('goshen_wallets')) {
                app(GoshenWalletService::class)->walletFor($user);
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
            if ($credentials->isSyncing() || (! $user->wasRecentlyCreated && ! $user->wasChanged(['email', 'password']))) {
                return;
            }

            $mobile = app(LinkedMobileAccountService::class)->forAdmin($user);
            if ($mobile) {
                $credentials->syncMobileFromAdmin($user, $mobile);
            }
        });

        PaymentTransaction::saved(fn (PaymentTransaction $transaction): null => $this->syncTransactionEntry(
            fn () => app(GoshenTransactionEntrySyncService::class)->syncPaymentTransaction($transaction),
        ));

        GoshenWalletLedgerEntry::saved(fn (GoshenWalletLedgerEntry $entry): null => $this->syncTransactionEntry(
            fn () => app(GoshenTransactionEntrySyncService::class)->syncWalletLedgerEntry($entry),
        ));

        GoshenVoucherUsage::saved(fn (GoshenVoucherUsage $usage): null => $this->syncTransactionEntry(
            fn () => app(GoshenTransactionEntrySyncService::class)->syncVoucherUsage($usage),
        ));

        Donation::saved(fn (Donation $donation): null => $this->syncTransactionEntry(
            fn () => app(GoshenTransactionEntrySyncService::class)->syncDonation($donation),
        ));

        DynamicFormSubmission::saved(fn (DynamicFormSubmission $submission): null => $this->syncTransactionEntry(
            fn () => app(GoshenTransactionEntrySyncService::class)->syncDynamicFormSubmission($submission),
        ));

        if (class_exists(CampaignContribution::class)) {
            CampaignContribution::saved(fn (CampaignContribution $contribution): null => $this->syncTransactionEntry(
                fn () => app(GoshenTransactionEntrySyncService::class)->syncFundraisingContribution($contribution),
            ));
        }
    }

    private function syncTransactionEntry(callable $callback): null
    {
        if (! Schema::hasTable('goshen_transaction_entries')) {
            return null;
        }

        try {
            $callback();
        } catch (Throwable $exception) {
            report($exception);
        }

        return null;
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
