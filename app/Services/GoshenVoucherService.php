<?php

namespace App\Services;

use App\Models\GoshenVoucher;
use App\Models\GoshenVoucherUsage;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\PaymentSettlementService;
use RuntimeException;

class GoshenVoucherService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly PaymentSettlementService $settlement,
        private readonly GoshenSingleFullPaymentService $fullPayments,
    ) {}

    public function normalizeCode(string $code): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper(trim($code))) ?: '';
    }

    public function hashCode(string $code): string
    {
        return hash_hmac('sha256', $this->normalizeCode($code), (string) config('services.goshen_vouchers.pepper', config('app.key')));
    }

    public function generateCode(): string
    {
        do {
            $raw = $this->randomSegment(4).$this->randomSegment(4).$this->randomSegment(4).$this->randomSegment(4);
            $code = 'GSH-'.substr($raw, 0, 4).'-'.substr($raw, 4, 4).'-'.substr($raw, 8, 4).'-'.substr($raw, 12, 4);
        } while (GoshenVoucher::query()->where('code_hash', $this->hashCode($code))->exists());

        return $code;
    }

    /**
     * @return array{voucher: GoshenVoucher, code: string}
     */
    public function createVoucher(array $data, ?MobileUser $mobileActor = null, ?User $adminActor = null): array
    {
        $purpose = (string) ($data['purpose'] ?? GoshenVoucher::PURPOSE_PAYMENTS);
        if (! array_key_exists($purpose, GoshenVoucher::purposeOptions())) {
            throw new RuntimeException('Voucher purpose is not valid.');
        }

        $redemptionType = (string) ($data['redemption_type'] ?? GoshenVoucher::REDEMPTION_FIXED);
        if (! array_key_exists($redemptionType, GoshenVoucher::redemptionTypeOptions())) {
            throw new RuntimeException('Voucher category is not valid.');
        }

        if ($purpose === GoshenVoucher::PURPOSE_WALLET_FUNDING && $redemptionType === GoshenVoucher::REDEMPTION_POOL) {
            throw new RuntimeException('Pool balance vouchers are only available for Goshen payment vouchers.');
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        $rawMaxUses = array_key_exists('max_uses', $data) ? (int) $data['max_uses'] : null;
        $maxUses = $redemptionType === GoshenVoucher::REDEMPTION_POOL
            ? ($rawMaxUses !== null && $rawMaxUses > 1 ? max(2, $rawMaxUses) : 1000)
            : max(1, (int) ($data['max_uses'] ?? 1));

        $code = isset($data['code']) && is_string($data['code']) && trim($data['code']) !== ''
            ? $data['code']
            : $this->generateCode();

        $normalized = $this->normalizeCode($code);
        if (strlen($normalized) < 8) {
            throw new RuntimeException('Voucher code must contain at least 8 letters or numbers.');
        }
        $formattedCode = $this->formatCode($normalized);

        $voucher = GoshenVoucher::query()->create([
            'event_id' => $data['event_id'] ?? null,
            'purpose' => $purpose,
            'redemption_type' => $redemptionType,
            'created_by_id' => $adminActor?->id,
            'created_by_mobile_user_id' => $mobileActor?->id,
            'label' => $data['label'] ?? null,
            'batch_reference' => $data['batch_reference'] ?? null,
            'code_hash' => $this->hashCode($normalized),
            'code_suffix' => substr($normalized, -6),
            'redemption_code' => $formattedCode,
            'currency' => strtoupper((string) ($data['currency'] ?? 'GBP')),
            'amount' => $amount,
            'remaining_amount' => $redemptionType === GoshenVoucher::REDEMPTION_POOL ? $amount : null,
            'max_uses' => $maxUses,
            'used_count' => 0,
            'status' => GoshenVoucher::STATUS_ACTIVE,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        if ($amount <= 0) {
            $voucher->delete();
            throw new RuntimeException('Voucher amount must be greater than zero.');
        }

        return ['voucher' => $voucher, 'code' => $formattedCode];
    }

    /**
     * @return array<int, array{voucher: GoshenVoucher, code: string}>
     */
    public function createBulk(array $data, ?MobileUser $mobileActor = null, ?User $adminActor = null): array
    {
        $quantity = min(500, max(1, (int) ($data['quantity'] ?? 1)));
        $batch = $data['batch_reference'] ?? ('GSV-'.Str::upper((string) Str::ulid()));
        $created = [];

        DB::transaction(function () use ($quantity, $batch, $data, $mobileActor, $adminActor, &$created): void {
            for ($index = 0; $index < $quantity; $index++) {
                $created[] = $this->createVoucher(array_merge($data, [
                    'code' => null,
                    'batch_reference' => $batch,
                    'metadata' => array_filter(array_merge($data['metadata'] ?? [], [
                        'batch_index' => $index + 1,
                        'batch_quantity' => $quantity,
                    ])),
                ]), $mobileActor, $adminActor);
            }
        });

        return $created;
    }

    public function verify(string $code, ?Event $event = null, ?float $amount = null, ?string $currency = null): array
    {
        $ambiguousSuffix = false;
        $voucher = $this->findVoucherByCode($code, ambiguousSuffix: $ambiguousSuffix);

        if (! $voucher) {
            return $this->verificationPayload(
                false,
                $ambiguousSuffix
                    ? 'This voucher suffix matches more than one voucher. Enter the full voucher code.'
                    : 'This voucher code is not valid.',
            );
        }

        try {
            $this->assertVoucherPurpose($voucher, GoshenVoucher::PURPOSE_PAYMENTS);
            $this->assertVoucherCanPay($voucher, $event, $amount, $currency);
        } catch (RuntimeException $exception) {
            return $this->verificationPayload(false, $exception->getMessage(), $voucher);
        }

        return $this->verificationPayload(true, 'Voucher is valid.', $voucher);
    }

    public function redeemForBooking(
        Booking $booking,
        PaymentInstallment $installment,
        string $code,
        MobileUser $beneficiary,
        ?MobileUser $redeemedBy = null,
        string $source = 'mobile_registration',
        ?User $adminActor = null,
        array $context = [],
    ): GoshenVoucherUsage {
        return DB::transaction(function () use ($booking, $installment, $code, $beneficiary, $redeemedBy, $source, $adminActor, $context): GoshenVoucherUsage {
            $booking = Booking::query()
                ->with(['event', 'installments'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $installment = PaymentInstallment::query()
                ->whereKey($installment->id)
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->fullPayments->assertPayable($booking, $installment);
            $this->fullPayments->assertNoLiveExternalCheckout($installment);

            $bookingStatus = $booking->status?->value ?? (string) $booking->status;
            if (in_array($bookingStatus, [BookingStatus::Paid->value, BookingStatus::Cancelled->value, BookingStatus::Refunded->value], true)) {
                throw new RuntimeException('This registration is not open for voucher payment.');
            }

            $installmentStatus = $installment->status?->value ?? (string) $installment->status;
            if (in_array($installmentStatus, [InstallmentStatus::Paid->value, InstallmentStatus::Cancelled->value, InstallmentStatus::Refunded->value], true)) {
                throw new RuntimeException('This payment has already been completed.');
            }

            $amountDue = round(max(0, (float) $installment->amount - (float) $installment->paid_amount), 2);
            if ($amountDue <= 0) {
                throw new RuntimeException('This payment has no outstanding balance.');
            }

            $ambiguousSuffix = false;
            $voucher = $this->findVoucherByCode($code, lockForUpdate: true, ambiguousSuffix: $ambiguousSuffix);

            if (! $voucher) {
                throw new RuntimeException($ambiguousSuffix
                    ? 'This voucher suffix matches more than one voucher. Enter the full voucher code.'
                    : 'This voucher code is not valid.');
            }

            $this->assertVoucherPurpose($voucher, GoshenVoucher::PURPOSE_PAYMENTS);
            $this->assertVoucherCanPay($voucher, $booking->event, $amountDue, (string) $booking->currency);
            $redemptionType = $voucher->redemption_type ?: GoshenVoucher::REDEMPTION_FIXED;
            $poolBalanceBefore = $voucher->isPoolVoucher() ? round((float) $voucher->remaining_amount, 2) : null;
            $poolBalanceAfter = $poolBalanceBefore !== null ? round(max(0, $poolBalanceBefore - $amountDue), 2) : null;

            $transaction = PaymentTransaction::query()->create([
                'booking_id' => $booking->id,
                'installment_id' => $installment->id,
                'gateway' => 'voucher',
                'provider_reference' => 'gsv_'.Str::ulid(),
                'currency' => $booking->currency,
                'amount' => $amountDue,
                'status' => 'pending',
                'payload' => [
                    'source' => $source,
                    'voucher_id' => $voucher->id,
                    'voucher_code_suffix' => $voucher->code_suffix,
                    'voucher_redemption_type' => $redemptionType,
                    'voucher_balance_before' => $poolBalanceBefore,
                    'voucher_balance_after' => $poolBalanceAfter,
                    'beneficiary_mobile_user_id' => $beneficiary->id,
                    'redeemed_by_mobile_user_id' => $redeemedBy?->id,
                    'redeemed_by_id' => $adminActor?->id,
                    'request_ip' => $context['request_ip'] ?? null,
                    'request_user_agent' => $context['request_user_agent'] ?? null,
                ],
            ]);

            $usage = GoshenVoucherUsage::query()->create([
                'voucher_id' => $voucher->id,
                'event_id' => $booking->event_id,
                'booking_id' => $booking->id,
                'payment_installment_id' => $installment->id,
                'payment_transaction_id' => $transaction->id,
                'mobile_user_id' => $beneficiary->id,
                'redeemed_by_mobile_user_id' => $redeemedBy?->id,
                'redeemed_by_id' => $adminActor?->id,
                'code_suffix' => $voucher->code_suffix,
                'currency' => $booking->currency,
                'amount' => $amountDue,
                'source' => $source,
                'status' => GoshenVoucherUsage::STATUS_APPLIED,
                'metadata' => [
                    'booking_public_id' => $booking->public_id,
                    'event_name' => $booking->event?->name,
                    'voucher_redemption_type' => $redemptionType,
                    'voucher_balance_before' => $poolBalanceBefore,
                    'voucher_balance_after' => $poolBalanceAfter,
                    'request_ip' => $context['request_ip'] ?? null,
                    'request_user_agent' => $context['request_user_agent'] ?? null,
                ],
            ]);

            $this->markVoucherRedeemed($voucher, $amountDue);

            $installment->forceFill([
                'metadata' => array_merge($installment->metadata ?? [], [
                    'payment_mode' => 'voucher',
                    'voucher_id' => $voucher->id,
                    'voucher_code_suffix' => $voucher->code_suffix,
                    'voucher_redemption_type' => $redemptionType,
                    'voucher_balance_after' => $poolBalanceAfter,
                    'voucher_usage_id' => $usage->id,
                ]),
            ])->save();

            $booking->forceFill([
                'metadata' => array_merge($booking->metadata ?? [], [
                    'payment_mode' => 'voucher',
                    'paid_with_voucher' => true,
                    'voucher_id' => $voucher->id,
                    'voucher_code_suffix' => $voucher->code_suffix,
                    'voucher_redemption_type' => $redemptionType,
                    'voucher_balance_after' => $poolBalanceAfter,
                    'voucher_usage_id' => $usage->id,
                ]),
            ])->save();

            $this->settlement->markPaid($transaction, $amountDue, (string) $booking->currency);

            return $usage->fresh(['voucher', 'booking', 'paymentTransaction']) ?? $usage;
        });
    }

    public function redeemForWalletTopUp(
        GoshenWallet $wallet,
        string $code,
        MobileUser $beneficiary,
        ?MobileUser $redeemedBy = null,
        array $context = [],
    ): GoshenVoucherUsage {
        return DB::transaction(function () use ($wallet, $code, $beneficiary, $redeemedBy, $context): GoshenVoucherUsage {
            $lockedWallet = GoshenWallet::query()
                ->whereKey($wallet->id)
                ->where('mobile_user_id', $beneficiary->id)
                ->lockForUpdate()
                ->firstOrFail();

            $ambiguousSuffix = false;
            $voucher = $this->findVoucherByCode($code, lockForUpdate: true, ambiguousSuffix: $ambiguousSuffix);

            if (! $voucher) {
                throw new RuntimeException($ambiguousSuffix
                    ? 'This voucher suffix matches more than one voucher. Enter the full voucher code.'
                    : 'This voucher code is not valid.');
            }

            $this->assertVoucherPurpose($voucher, GoshenVoucher::PURPOSE_WALLET_FUNDING);
            if ($voucher->isPoolVoucher()) {
                throw new RuntimeException('Pool balance vouchers cannot be used for wallet funding.');
            }

            $this->assertVoucherCanPay($voucher, null, null, (string) $lockedWallet->currency);

            $amount = round((float) $voucher->amount, 2);
            if ($amount <= 0) {
                throw new RuntimeException('This voucher cannot be used for wallet funding.');
            }

            $reference = 'gw_voucher_'.Str::ulid();
            $lockedWallet->forceFill([
                'balance' => round(((float) $lockedWallet->balance) + $amount, 2),
            ])->save();

            $entry = $lockedWallet->ledgerEntries()->create([
                'type' => 'voucher_top_up',
                'status' => 'paid',
                'currency' => strtoupper((string) $voucher->currency),
                'amount' => $amount,
                'gateway' => 'voucher',
                'provider_reference' => $reference,
                'metadata' => [
                    'source' => 'wallet_voucher_top_up',
                    'voucher_id' => $voucher->id,
                    'voucher_code_suffix' => $voucher->code_suffix,
                    'voucher_redemption_type' => $voucher->redemption_type ?: GoshenVoucher::REDEMPTION_FIXED,
                    'request_ip' => $context['request_ip'] ?? null,
                    'request_user_agent' => $context['request_user_agent'] ?? null,
                ],
                'settled_at' => now(),
            ]);

            $usage = GoshenVoucherUsage::query()->create([
                'voucher_id' => $voucher->id,
                'mobile_user_id' => $beneficiary->id,
                'redeemed_by_mobile_user_id' => $redeemedBy?->id,
                'code_suffix' => $voucher->code_suffix,
                'currency' => strtoupper((string) $voucher->currency),
                'amount' => $amount,
                'source' => 'wallet_top_up',
                'status' => GoshenVoucherUsage::STATUS_APPLIED,
                'metadata' => [
                    'wallet_id' => $lockedWallet->id,
                    'wallet_ledger_entry_id' => $entry->id,
                    'wallet_balance_after' => (float) $lockedWallet->balance,
                    'voucher_redemption_type' => $voucher->redemption_type ?: GoshenVoucher::REDEMPTION_FIXED,
                    'request_ip' => $context['request_ip'] ?? null,
                    'request_user_agent' => $context['request_user_agent'] ?? null,
                ],
            ]);

            $this->markVoucherRedeemed($voucher, $amount);

            return $usage->fresh(['voucher', 'mobileUser', 'redeemedByMobileUser']) ?? $usage;
        });
    }

    public function voucherPayload(GoshenVoucher $voucher, bool $includeInternal = false): array
    {
        return array_filter([
            'id' => $voucher->id,
            'event_id' => $voucher->event_id,
            'purpose' => $voucher->purpose,
            'redemption_type' => $voucher->redemption_type ?: GoshenVoucher::REDEMPTION_FIXED,
            'redemption_type_label' => GoshenVoucher::redemptionTypeOptions()[$voucher->redemption_type ?: GoshenVoucher::REDEMPTION_FIXED] ?? 'Fixed amount voucher',
            'label' => $voucher->label,
            'batch_reference' => $voucher->batch_reference,
            'code_suffix' => $voucher->code_suffix,
            'redemption_code' => $includeInternal ? $voucher->redemption_code : null,
            'currency' => $voucher->currency,
            'amount' => (float) $voucher->amount,
            'remaining_amount' => $voucher->isPoolVoucher() ? (float) $voucher->remaining_amount : null,
            'available_amount' => $voucher->availableAmount(),
            'max_uses' => (int) $voucher->max_uses,
            'used_count' => (int) $voucher->used_count,
            'remaining_uses' => max(0, (int) $voucher->max_uses - (int) $voucher->used_count),
            'status' => $voucher->status,
            'starts_at' => $voucher->starts_at?->toIso8601String(),
            'expires_at' => $voucher->expires_at?->toIso8601String(),
            'created_at' => $voucher->created_at?->toIso8601String(),
            'metadata' => $includeInternal ? $voucher->metadata : null,
        ], fn ($value) => $value !== null);
    }

    public function usagePayload(GoshenVoucherUsage $usage): array
    {
        $usage->loadMissing(['voucher', 'event', 'booking', 'mobileUser', 'redeemedByMobileUser']);

        return [
            'id' => $usage->id,
            'voucher_id' => $usage->voucher_id,
            'code_suffix' => $usage->code_suffix,
            'event' => $usage->event ? [
                'id' => $usage->event->id,
                'public_id' => $usage->event->public_id,
                'name' => $usage->event->name,
            ] : null,
            'booking' => $usage->booking ? [
                'id' => $usage->booking->id,
                'public_id' => $usage->booking->public_id,
                'customer_name' => $usage->booking->customer_name,
                'customer_email' => $usage->booking->customer_email,
            ] : null,
            'member' => $usage->mobileUser ? [
                'id' => $usage->mobileUser->id,
                'name' => $usage->mobileUser->name,
                'email' => $usage->mobileUser->email,
            ] : null,
            'redeemed_by' => $usage->redeemedByMobileUser ? [
                'id' => $usage->redeemedByMobileUser->id,
                'name' => $usage->redeemedByMobileUser->name,
                'email' => $usage->redeemedByMobileUser->email,
            ] : null,
            'currency' => $usage->currency,
            'amount' => (float) $usage->amount,
            'source' => $usage->source,
            'status' => $usage->status,
            'created_at' => $usage->created_at?->toIso8601String(),
        ];
    }

    private function assertVoucherCanPay(GoshenVoucher $voucher, ?Event $event, ?float $amount, ?string $currency): void
    {
        if ($voucher->status !== GoshenVoucher::STATUS_ACTIVE) {
            throw new RuntimeException('This voucher is not active.');
        }

        if ((int) $voucher->used_count >= (int) $voucher->max_uses) {
            throw new RuntimeException($voucher->isPoolVoucher()
                ? 'This pool voucher has reached its redemption limit.'
                : 'This voucher has already been used.');
        }

        if ($voucher->starts_at && $voucher->starts_at->isFuture()) {
            throw new RuntimeException('This voucher is not active yet.');
        }

        if ($voucher->expires_at && $voucher->expires_at->isPast()) {
            throw new RuntimeException('This voucher has expired.');
        }

        if ($event && $voucher->event_id && (int) $voucher->event_id !== (int) $event->id) {
            throw new RuntimeException('This voucher is not valid for the selected Goshen Retreat edition.');
        }

        if ($currency !== null && strtoupper((string) $voucher->currency) !== strtoupper($currency)) {
            throw new RuntimeException("This voucher is in {$voucher->currency}, but this payment is in {$currency}.");
        }

        if ($amount === null) {
            return;
        }

        if ($voucher->isPoolVoucher()) {
            if ((float) $voucher->remaining_amount <= 0) {
                throw new RuntimeException('This pool voucher has no remaining balance.');
            }

            if ((float) $voucher->remaining_amount + 0.01 < (float) $amount) {
                throw new RuntimeException('This pool voucher remaining balance is lower than the payment due.');
            }

            return;
        }

        if ((float) $voucher->amount + 0.01 < (float) $amount) {
            throw new RuntimeException('This voucher amount is lower than the payment due.');
        }
    }

    private function findVoucherByCode(string $code, bool $lockForUpdate = false, bool &$ambiguousSuffix = false): ?GoshenVoucher
    {
        $ambiguousSuffix = false;
        $normalized = $this->normalizeCode($code);

        if ($normalized === '') {
            return null;
        }

        $exactQuery = GoshenVoucher::query()->where('code_hash', $this->hashCode($normalized));
        if ($lockForUpdate) {
            $exactQuery->lockForUpdate();
        }

        $voucher = $exactQuery->first();
        if ($voucher) {
            return $voucher;
        }

        if (strlen($normalized) !== 6) {
            return null;
        }

        $suffixQuery = GoshenVoucher::query()
            ->where('code_suffix', $normalized)
            ->orderBy('id');

        if ($lockForUpdate) {
            $suffixQuery->lockForUpdate();
        }

        $matches = $suffixQuery->limit(2)->get();
        if ($matches->count() === 1) {
            return $matches->first();
        }

        $ambiguousSuffix = $matches->count() > 1;

        return null;
    }

    private function markVoucherRedeemed(GoshenVoucher $voucher, float $amount): void
    {
        $usedCount = (int) $voucher->used_count + 1;
        $updates = [
            'used_count' => $usedCount,
        ];

        if ($voucher->isPoolVoucher()) {
            $remaining = round(max(0, (float) $voucher->remaining_amount - $amount), 2);
            $updates['remaining_amount'] = $remaining;
            if ($remaining <= 0.01 || $usedCount >= (int) $voucher->max_uses) {
                $updates['status'] = GoshenVoucher::STATUS_EXHAUSTED;
            }
        } elseif ($usedCount >= (int) $voucher->max_uses) {
            $updates['status'] = GoshenVoucher::STATUS_EXHAUSTED;
        }

        $voucher->forceFill($updates)->save();
    }

    private function assertVoucherPurpose(GoshenVoucher $voucher, string $requiredPurpose): void
    {
        if ($voucher->purpose === $requiredPurpose) {
            return;
        }

        $message = $voucher->purpose === GoshenVoucher::PURPOSE_WALLET_FUNDING
            ? 'This voucher is only valid for wallet funding.'
            : 'This voucher is only valid for payments.';

        throw new RuntimeException($message);
    }

    private function verificationPayload(bool $valid, string $message, ?GoshenVoucher $voucher = null): array
    {
        return [
            'valid' => $valid,
            'message' => $message,
            'voucher' => $voucher ? $this->voucherPayload($voucher) : null,
        ];
    }

    private function randomSegment(int $length): string
    {
        $segment = '';
        $max = strlen(self::CODE_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $segment .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $segment;
    }

    private function formatCode(string $normalized): string
    {
        if (str_starts_with($normalized, 'GSH') && strlen($normalized) > 3) {
            return 'GSH-'.implode('-', str_split(substr($normalized, 3), 4));
        }

        return implode('-', str_split($normalized, 4));
    }
}
