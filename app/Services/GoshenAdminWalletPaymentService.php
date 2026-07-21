<?php

namespace App\Services;

use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Models\User;
use App\Support\AdminPermissions;
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
        return $this->settleWallet(
            $booking,
            $fullPaymentRecord,
            $wallet,
            $payer,
            $beneficiary,
            $admin,
            $context,
        );
    }

    /**
     * Settle an admin-authorized retreat ticket from the selected member's own wallet.
     *
     * @param  array{confirmed: bool, authorization_method: string, authorization_note: string}  $authorization
     * @param  array<string, mixed>  $context
     */
    public function settleMemberWallet(
        Booking $booking,
        PaymentInstallment $fullPaymentRecord,
        GoshenWallet $wallet,
        MobileUser $member,
        User $admin,
        array $authorization,
        array $context = [],
    ): PaymentTransaction {
        return $this->settleWallet(
            $booking,
            $fullPaymentRecord,
            $wallet,
            $member,
            $member,
            $admin,
            $context,
            $authorization,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array{confirmed: bool, authorization_method: string, authorization_note: string}|null  $memberWalletAuthorization
     */
    private function settleWallet(
        Booking $booking,
        PaymentInstallment $fullPaymentRecord,
        GoshenWallet $wallet,
        MobileUser $payer,
        MobileUser $beneficiary,
        User $admin,
        array $context = [],
        ?array $memberWalletAuthorization = null,
    ): PaymentTransaction {
        return DB::transaction(function () use (
            $booking,
            $fullPaymentRecord,
            $wallet,
            $payer,
            $beneficiary,
            $admin,
            $context,
            $memberWalletAuthorization,
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

            $isMemberWalletCharge = $memberWalletAuthorization !== null;

            if ($isMemberWalletCharge) {
                $this->assertMemberWalletChargeAllowed($admin, $payer, $beneficiary, $memberWalletAuthorization);
            } elseif (! $payer->canUseCommunity()
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

            $source = $isMemberWalletCharge
                ? 'filament_member_wallet_charge'
                : 'filament_admin_ticket_issue';
            $reference = ($isMemberWalletCharge ? 'gw_member_ticket_' : 'gw_admin_ticket_').Str::ulid();
            $balanceBefore = round((float) $wallet->balance, 2);
            $wallet->forceFill([
                'balance' => round($balanceBefore - $amount, 2),
            ])->save();

            $memberWalletAudit = $isMemberWalletCharge
                ? $this->memberWalletAuthorizationAudit($memberWalletAuthorization, $admin, $payer, $wallet, $balanceBefore)
                : null;

            $wallet->ledgerEntries()->create([
                'type' => 'retreat_payment',
                'status' => 'paid',
                'currency' => $currency,
                'amount' => $amount,
                'gateway' => 'wallet',
                'provider_reference' => $reference,
                'metadata' => array_filter([
                    'source' => $source,
                    'booking_id' => $booking->id,
                    'payer_mobile_user_id' => $payer->id,
                    'beneficiary_mobile_user_id' => $beneficiary->id,
                    'payer_admin_user_id' => $admin->id,
                    'charged_member_wallet_id' => $isMemberWalletCharge ? $wallet->id : null,
                    'wallet_balance_before' => $balanceBefore,
                    'wallet_balance_after' => round((float) $wallet->balance, 2),
                    'member_wallet_authorization' => $memberWalletAudit,
                    'request_ip' => $context['request_ip'] ?? null,
                    'request_user_agent' => $context['request_user_agent'] ?? null,
                ], fn (mixed $value): bool => $value !== null),
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
                'payload' => array_filter([
                    'source' => $source,
                    'wallet_id' => $wallet->id,
                    'payer_mobile_user_id' => $payer->id,
                    'beneficiary_mobile_user_id' => $beneficiary->id,
                    'payer_admin_user_id' => $admin->id,
                    'charged_member_wallet_id' => $isMemberWalletCharge ? $wallet->id : null,
                    'member_wallet_authorization' => $memberWalletAudit,
                    'request_ip' => $context['request_ip'] ?? null,
                    'request_user_agent' => $context['request_user_agent'] ?? null,
                ], fn (mixed $value): bool => $value !== null),
            ]);

            $this->settlements->markPaid($transaction, $amount, $currency);

            return $transaction->fresh();
        });
    }

    /**
     * @param  array{confirmed: bool, authorization_method: string, authorization_note: string}  $authorization
     */
    private function assertMemberWalletChargeAllowed(
        User $admin,
        MobileUser $payer,
        MobileUser $beneficiary,
        array $authorization,
    ): void {
        if (! $admin->hasRole('super_admin') && ! $admin->can(AdminPermissions::GOSHEN_MEMBER_WALLET_CHARGE)) {
            throw ValidationException::withMessages([
                'payment_method' => 'You are not authorized to charge a member wallet.',
            ]);
        }

        if (! $payer->canUseCommunity() || (int) $payer->id !== (int) $beneficiary->id) {
            throw ValidationException::withMessages([
                'payment_method' => 'Only the selected active member wallet can be charged for this ticket.',
            ]);
        }

        $method = trim((string) ($authorization['authorization_method'] ?? ''));
        $note = trim((string) ($authorization['authorization_note'] ?? ''));
        if (! filter_var($authorization['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || ! in_array($method, ['registered_contact', 'in_person', 'church_record', 'other_verified_process'], true)
            || str($note)->length() < 20) {
            throw ValidationException::withMessages([
                'member_authorization_note' => 'Record the member authorization method and a meaningful note before charging their wallet.',
            ]);
        }
    }

    /**
     * @param  array{confirmed: bool, authorization_method: string, authorization_note: string}  $authorization
     * @return array<string, mixed>
     */
    private function memberWalletAuthorizationAudit(
        array $authorization,
        User $admin,
        MobileUser $member,
        GoshenWallet $wallet,
        float $balanceBefore,
    ): array {
        return [
            'confirmed' => true,
            'authorization_method' => trim((string) $authorization['authorization_method']),
            'authorization_note' => trim((string) $authorization['authorization_note']),
            'recorded_at' => now()->toIso8601String(),
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
            'member_mobile_user_id' => $member->id,
            'member_triumphant_id' => $member->triumphant_id,
            'wallet_id' => $wallet->id,
            'wallet_balance_before' => $balanceBefore,
        ];
    }
}
