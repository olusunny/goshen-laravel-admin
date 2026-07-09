<?php

namespace App\Services;

use App\Models\GoshenWallet;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\MobileUser;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Sunny\Fundraising\Contracts\WalletGatewayContract;
use Sunny\Fundraising\DTOs\WalletDebitResult;
use Sunny\Fundraising\Models\Campaign;

class FundraisingWalletGateway implements WalletGatewayContract
{
    public function __construct(
        private readonly GoshenWalletService $wallets,
        private readonly WalletSecurityResetService $walletSecurityResets,
    ) {}

    public function getBalance(mixed $user): int|float|string
    {
        if (! $user instanceof MobileUser) {
            return 0;
        }

        return (float) $this->wallets->walletFor($user)->balance;
    }

    public function debitForFundraisingContribution(
        mixed $user,
        int|float|string $amount,
        Campaign $campaign,
        array $metadata = [],
    ): WalletDebitResult {
        if (! $user instanceof MobileUser) {
            return WalletDebitResult::failure('Please sign in before contributing from your wallet.', 'unauthenticated');
        }

        if ($user->is_deleted || $user->is_blocked || ! $user->is_verified) {
            return WalletDebitResult::failure('Your account cannot use wallet contributions right now.', 'user_not_allowed');
        }

        $amount = round((float) $amount, 2);
        $currency = strtoupper((string) $campaign->currency);
        $contributionId = (string) ($metadata['contribution_id'] ?? '');
        $reference = 'fundraising_wallet_'.substr(hash('sha256', $user->id.'|'.$campaign->id.'|'.$contributionId), 0, 32);

        try {
            return DB::transaction(function () use ($user, $amount, $currency, $campaign, $metadata, $reference): WalletDebitResult {
                $this->walletSecurityResets->assertWalletActionsAllowed($user);

                $wallet = $this->wallets->walletFor($user);
                $lockedWallet = GoshenWallet::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = GoshenWalletLedgerEntry::query()
                    ->where('provider_reference', $reference)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->status === 'paid') {
                    return WalletDebitResult::success($existing->id, (float) $lockedWallet->balance, (string) $lockedWallet->currency);
                }

                if (strtoupper((string) $lockedWallet->currency) !== $currency) {
                    throw new RuntimeException('Your wallet currency does not match this campaign.');
                }

                if ((float) $lockedWallet->balance + 0.01 < $amount) {
                    throw new RuntimeException('Your wallet balance is not enough for this contribution.');
                }

                $lockedWallet->forceFill([
                    'balance' => round(((float) $lockedWallet->balance) - $amount, 2),
                ])->save();

                $entry = $lockedWallet->ledgerEntries()->create([
                    'type' => 'fundraising_payment',
                    'status' => 'paid',
                    'currency' => $currency,
                    'amount' => $amount,
                    'gateway' => 'wallet',
                    'provider_reference' => $reference,
                    'metadata' => array_merge($metadata, [
                        'source' => 'sunny_fundraising',
                        'campaign_id' => $campaign->id,
                        'campaign_slug' => $campaign->slug,
                        'campaign_title' => $campaign->title,
                    ]),
                    'settled_at' => now(),
                ]);

                return WalletDebitResult::success($entry->id, (float) $lockedWallet->balance, $currency);
            });
        } catch (RuntimeException $exception) {
            return WalletDebitResult::failure($exception->getMessage(), 'wallet_debit_failed');
        }
    }
}
