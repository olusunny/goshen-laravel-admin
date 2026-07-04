<?php

namespace App\Console\Commands;

use App\Models\GoshenWalletSavingsPlan;
use App\Services\GoshenRetreatNotificationService;
use App\Services\GoshenWalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessGoshenWalletTopUps extends Command
{
    protected $signature = 'goshen:process-wallet-topups {--limit=50}';

    protected $description = 'Process due Goshen wallet recurring top-ups for users with saved Stripe payment methods.';

    public function handle(GoshenWalletService $wallets, GoshenRetreatNotificationService $notifications): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;

        GoshenWalletSavingsPlan::query()
            ->with('wallet.user')
            ->where('status', 'active')
            ->whereNotNull('next_charge_at')
            ->where('next_charge_at', '<=', now())
            ->orderBy('next_charge_at')
            ->limit($limit)
            ->get()
            ->each(function (GoshenWalletSavingsPlan $plan) use ($wallets, $notifications, &$processed): void {
                try {
                    $entry = $wallets->chargeDuePlan($plan);
                    $processed++;

                    if (! $entry) {
                        $user = $plan->wallet?->user;
                        if ($user) {
                            $notifications->notifyUser(
                                $user,
                                'Goshen wallet top-up needs attention',
                                'Your scheduled Goshen Retreat wallet top-up could not run because we do not have a reusable card permission yet. Please open My Wallet and complete a secure top-up with saved card permission when convenient.',
                                'events'
                            );
                        }
                    } elseif ($entry->status !== 'paid') {
                        $user = $plan->wallet?->user;
                        if ($user) {
                            $willRetry = (bool) data_get($entry->metadata, 'will_retry');
                            $nextRetryAt = data_get($entry->metadata, 'next_retry_at');
                            $retriesRemaining = (int) data_get($entry->metadata, 'retries_remaining', 0);
                            $body = $willRetry
                                ? "Your scheduled Goshen Retreat wallet top-up did not go through. We will retry automatically".($nextRetryAt ? " at {$nextRetryAt}" : ' soon').". Retries remaining: {$retriesRemaining}. You can also open My Wallet to check your saved payment method."
                                : 'Your scheduled Goshen Retreat wallet top-up did not go through after the automatic retry attempts. The plan has been paused. Please open My Wallet to update your saved payment method or resume the plan.';

                            $notifications->notifyUser(
                                $user,
                                'Goshen wallet top-up failed',
                                $body,
                                'wallet'
                            );
                        }
                    }
                } catch (Throwable $exception) {
                    Log::error('Goshen wallet scheduled top-up failed', [
                        'plan_id' => $plan->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

        $this->info("Processed {$processed} wallet top-up plan(s).");

        return self::SUCCESS;
    }
}
