<?php

namespace App\Services;

use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\PaymentSettlementService;
use RuntimeException;

class GoshenAdminWalletPaymentService
{
    public function __construct(
        private readonly PaymentSettlementService $settlements,
        private readonly GoshenSingleFullPaymentService $fullPayments,
        private readonly WalletSecurityResetService $walletSecurityResets,
    ) {}

    public function settle(
        Booking $booking,
        PaymentInstallment $fullPaymentRecord,
        GoshenWallet $wallet,
        MobileUser $payer,
        MobileUser $beneficiary,
        User $admin,
        array $context = [],
    ): PaymentTransaction {
        return DB::transaction(function () use (
            $booking,
            $fullPaymentRecord,
            $wallet,
            $payer,
            $beneficiary,
            $admin,
            $context,
        ): PaymentTransaction {
            $admin = User::query()->whereKey($admin->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $fullPaymentRecord = PaymentInstallment::query()
                ->whereKey($fullPaymentRecord->id)
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();
            $payer = MobileUser::query()->whereKey($payer->id)->lockForUpdate()->firstOrFail();
            $beneficiary = MobileUser::query()->whereKey($beneficiary->id)->lockForUpdate()->firstOrFail();
            $wallet = GoshenWallet::query()
                ->whereKey($wallet->id)
                ->where('mobile_user_id', $payer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $payer->canUseCommunity()
                || strtolower(trim((string) $payer->email)) !== strtolower(trim((string) $admin->email))) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Your linked wallet account could not be verified.',
                ]);
            }

            if (! $beneficiary->canUseCommunity()) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Tickets can only be issued to active verified app members.',
                ]);
            }

            try {
                $this->walletSecurityResets->assertWalletActionsAllowed($payer);
                $this->fullPayments->assertPayable($booking, $fullPaymentRecord);
                $this->fullPayments->assertNoLiveExternalCheckout($fullPaymentRecord);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['payment_method' => $exception->getMessage()]);
            }

            $amount = round((float) $fullPaymentRecord->amount, 2);
            $currency = strtoupper((string) $booking->currency);

            if ($amount <= 0 || abs($amount - (float) $booking->total) > 0.009) {
                throw ValidationException::withMessages([
                    'payment_method' => 'The wallet must pay the complete ticket total.',
                ]);
            }

            if (strtoupper((string) $wallet->currency) !== $currency) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Your wallet currency does not match this ticket.',
                ]);
            }

            if ((float) $wallet->balance + 0.01 < $amount) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Your wallet balance is not enough for this ticket.',
                ]);
            }

            $reference = 'gw_admin_ticket_'.Str::ulid();
            $wallet->forceFill([
                'balance' => round(((float) $wallet->balance) - $amount, 2),
            ])->save();

            $wallet->ledgerEntries()->create([
                'type' => 'retreat_payment',
                'status' => 'paid',
                'currency' => $currency,
                'amount' => $amount,
                'gateway' => 'wallet',
                'provider_reference' => $reference,
                'metadata' => [
                    'source' => 'filament_admin_ticket_issue',
                    'booking_id' => $booking->id,
                    'payer_mobile_user_id' => $payer->id,
                    'beneficiary_mobile_user_id' => $beneficiary->id,
                    'payer_admin_user_id' => $admin->id,
                    'request_ip' => $context['request_ip'] ?? null,
                    'request_user_agent' => $context['request_user_agent'] ?? null,
                ],
                'settled_at' => now(),
            ]);

            $transaction = PaymentTransaction::query()->create([
                'booking_id' => $booking->id,
                'installment_id' => $fullPaymentRecord->id,
                'gateway' => 'wallet',
                'provider_reference' => $reference,
                'currency' => $currency,
                'amount' => $amount,
                'status' => 'pending',
                'payload' => [
                    'source' => 'filament_admin_ticket_issue',
                    'wallet_id' => $wallet->id,
                    'payer_mobile_user_id' => $payer->id,
                    'beneficiary_mobile_user_id' => $beneficiary->id,
                    'payer_admin_user_id' => $admin->id,
                    'request_ip' => $context['request_ip'] ?? null,
                    'request_user_agent' => $context['request_user_agent'] ?? null,
                ],
            ]);

            $this->settlements->markPaid($transaction, $amount, $currency);

            return $transaction->fresh();
        });
    }
}
