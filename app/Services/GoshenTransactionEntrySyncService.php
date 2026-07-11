<?php

namespace App\Services;

use App\Models\Donation;
use App\Models\DynamicFormSubmission;
use App\Models\GoshenTransactionEntry;
use App\Models\GoshenVoucherUsage;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\MobileUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Personal\EventInstallments\Models\PaymentTransaction;
use Sunny\Fundraising\Models\CampaignContribution;

class GoshenTransactionEntrySyncService
{
    public function syncAll(): array
    {
        $counts = [
            'payment_transactions' => 0,
            'wallet_ledger_entries' => 0,
            'voucher_usages' => 0,
            'donations' => 0,
            'dynamic_form_submissions' => 0,
            'fundraising_contributions' => 0,
        ];

        PaymentTransaction::query()
            ->with(['booking.event'])
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use (&$counts): void {
                foreach ($transactions as $transaction) {
                    $this->syncPaymentTransaction($transaction);
                    $counts['payment_transactions']++;
                }
            });

        GoshenWalletLedgerEntry::query()
            ->with(['wallet.user'])
            ->orderBy('id')
            ->chunkById(200, function ($entries) use (&$counts): void {
                foreach ($entries as $entry) {
                    $this->syncWalletLedgerEntry($entry);
                    $counts['wallet_ledger_entries']++;
                }
            });

        GoshenVoucherUsage::query()
            ->with(['mobileUser', 'voucher', 'event', 'booking', 'paymentTransaction'])
            ->orderBy('id')
            ->chunkById(200, function ($usages) use (&$counts): void {
                foreach ($usages as $usage) {
                    $this->syncVoucherUsage($usage);
                    $counts['voucher_usages']++;
                }
            });

        Donation::query()
            ->orderBy('id')
            ->chunkById(200, function ($donations) use (&$counts): void {
                foreach ($donations as $donation) {
                    $this->syncDonation($donation);
                    $counts['donations']++;
                }
            });

        DynamicFormSubmission::query()
            ->with(['mobileUser', 'dynamicForm'])
            ->whereNotNull('amount')
            ->orderBy('id')
            ->chunkById(200, function ($submissions) use (&$counts): void {
                foreach ($submissions as $submission) {
                    $this->syncDynamicFormSubmission($submission);
                    $counts['dynamic_form_submissions']++;
                }
            });

        if (class_exists(CampaignContribution::class)) {
            CampaignContribution::query()
                ->with(['campaign', 'user'])
                ->orderBy('id')
                ->chunkById(200, function ($contributions) use (&$counts): void {
                    foreach ($contributions as $contribution) {
                        $this->syncFundraisingContribution($contribution);
                        $counts['fundraising_contributions']++;
                    }
                });
        }

        return $counts;
    }

    public function syncPaymentTransaction(PaymentTransaction $transaction): GoshenTransactionEntry
    {
        $transaction->loadMissing(['booking.event']);

        $booking = $transaction->booking;
        $user = $booking?->customer_id ? MobileUser::query()->find($booking->customer_id) : null;
        $payload = $this->metadataArray($transaction->payload);
        $occurredAt = $transaction->paid_at ?? $transaction->created_at ?? now();

        return $this->upsert($transaction, [
            'mobile_user_id' => $user?->id,
            'source' => 'retreat_payment',
            'source_reference' => $transaction->provider_reference ?: $transaction->public_id,
            'transaction_kind' => 'payment',
            'direction' => 'credit',
            'counts_toward_revenue' => $this->isPaidStatus($transaction->status),
            'label' => trim(collect([
                $booking?->event?->name,
                $booking?->public_id ? "Booking {$booking->public_id}" : null,
            ])->filter()->implode(' · ')) ?: 'Goshen Retreat payment',
            'payer_name' => $user?->name ?? $booking?->customer_name,
            'payer_email' => $user?->email ?? $booking?->customer_email,
            'payer_phone' => $user?->phone ?? $booking?->customer_phone,
            'payment_provider' => strtolower((string) $transaction->gateway),
            'gateway' => strtolower((string) $transaction->gateway),
            'status' => (string) $transaction->status,
            'currency' => strtoupper((string) $transaction->currency),
            'amount' => (float) $transaction->amount,
            'initiated_at' => $transaction->created_at,
            'settled_at' => $transaction->paid_at,
            'occurred_at' => $occurredAt,
            ...$this->ipFields($payload),
            'metadata' => [
                'booking_id' => $booking?->id,
                'booking_public_id' => $booking?->public_id,
                'event_id' => $booking?->event_id,
                'event_name' => $booking?->event?->name,
                'installment_id' => $transaction->installment_id,
                'payload' => $payload,
            ],
        ]);
    }

    public function syncWalletLedgerEntry(GoshenWalletLedgerEntry $entry): GoshenTransactionEntry
    {
        $entry->loadMissing(['wallet.user']);

        $user = $entry->wallet?->user;
        $metadata = $this->metadataArray($entry->metadata);
        $occurredAt = $entry->settled_at ?? $entry->created_at ?? now();

        return $this->upsert($entry, [
            'mobile_user_id' => $user?->id,
            'source' => 'wallet_ledger',
            'source_reference' => $entry->provider_reference,
            'transaction_kind' => 'wallet_movement',
            'direction' => $this->walletDirection((string) $entry->type),
            'counts_toward_revenue' => false,
            'label' => $this->walletLabel((string) $entry->type),
            'payer_name' => $user?->name,
            'payer_email' => $user?->email,
            'payer_phone' => $user?->phone,
            'payment_provider' => strtolower((string) ($entry->gateway ?: 'wallet')),
            'gateway' => strtolower((string) ($entry->gateway ?: 'wallet')),
            'status' => (string) $entry->status,
            'currency' => strtoupper((string) $entry->currency),
            'amount' => (float) $entry->amount,
            'initiated_at' => $entry->created_at,
            'settled_at' => $entry->settled_at,
            'occurred_at' => $occurredAt,
            ...$this->ipFields($metadata),
            'metadata' => [
                'wallet_id' => $entry->wallet_id,
                'wallet_type' => $entry->type,
                'ledger_metadata' => $metadata,
            ],
        ]);
    }

    public function syncVoucherUsage(GoshenVoucherUsage $usage): GoshenTransactionEntry
    {
        $usage->loadMissing(['mobileUser', 'voucher', 'event', 'booking', 'paymentTransaction']);

        $user = $usage->mobileUser;
        $metadata = $this->metadataArray($usage->metadata);
        $occurredAt = $usage->created_at ?? now();
        $reference = $usage->paymentTransaction?->provider_reference
            ?: Arr::get($metadata, 'wallet_ledger_entry_id')
            ?: $usage->code_suffix;

        return $this->upsert($usage, [
            'mobile_user_id' => $user?->id,
            'source' => 'voucher_usage',
            'source_reference' => (string) $reference,
            'transaction_kind' => 'voucher_redemption',
            'direction' => 'neutral',
            'counts_toward_revenue' => false,
            'label' => trim(collect([
                $usage->voucher?->label ?: 'Voucher',
                $usage->event?->name,
            ])->filter()->implode(' · ')),
            'payer_name' => $user?->name,
            'payer_email' => $user?->email,
            'payer_phone' => $user?->phone,
            'payment_provider' => 'voucher',
            'gateway' => 'voucher',
            'status' => (string) $usage->status,
            'currency' => strtoupper((string) $usage->currency),
            'amount' => (float) $usage->amount,
            'initiated_at' => $usage->created_at,
            'settled_at' => $usage->created_at,
            'occurred_at' => $occurredAt,
            ...$this->ipFields($metadata),
            'metadata' => [
                'voucher_id' => $usage->voucher_id,
                'voucher_code_suffix' => $usage->code_suffix,
                'booking_id' => $usage->booking_id,
                'booking_public_id' => $usage->booking?->public_id,
                'payment_transaction_id' => $usage->payment_transaction_id,
                'payment_reference' => $usage->paymentTransaction?->provider_reference,
                'usage_metadata' => $metadata,
            ],
        ]);
    }

    public function syncDonation(Donation $donation): GoshenTransactionEntry
    {
        $metadata = $this->metadataArray($donation->metadata);
        $user = $donation->email
            ? MobileUser::query()->where('email', $donation->email)->first()
            : null;
        $occurredAt = $donation->paid_at ?? $donation->created_at ?? now();

        return $this->upsert($donation, [
            'mobile_user_id' => $user?->id,
            'source' => 'giving',
            'source_reference' => $donation->reference,
            'transaction_kind' => 'payment',
            'direction' => 'credit',
            'counts_toward_revenue' => $this->isPaidStatus($donation->status),
            'label' => 'Giving payment',
            'payer_name' => $user?->name ?? $donation->name,
            'payer_email' => $user?->email ?? $donation->email,
            'payer_phone' => $user?->phone ?? $donation->phone,
            'payment_provider' => strtolower((string) $donation->provider),
            'gateway' => strtolower((string) $donation->provider),
            'status' => (string) $donation->status,
            'currency' => strtoupper((string) $donation->currency),
            'amount' => (float) $donation->amount,
            'initiated_at' => $donation->created_at,
            'settled_at' => $donation->paid_at,
            'occurred_at' => $occurredAt,
            ...$this->ipFields($metadata),
            'metadata' => [
                'donation_id' => $donation->id,
                'donation_category_id' => $donation->donation_category_id ?? null,
                'donation_metadata' => $metadata,
            ],
        ]);
    }

    public function syncDynamicFormSubmission(DynamicFormSubmission $submission): GoshenTransactionEntry
    {
        $submission->loadMissing(['mobileUser', 'dynamicForm']);

        $metadata = $this->metadataArray($submission->metadata);
        $user = $submission->mobileUser;
        $occurredAt = $submission->paid_at ?? $submission->submitted_at ?? $submission->created_at ?? now();

        return $this->upsert($submission, [
            'mobile_user_id' => $user?->id,
            'source' => 'dynamic_form',
            'source_reference' => $submission->provider_reference ?: $submission->reference,
            'transaction_kind' => 'payment',
            'direction' => 'credit',
            'counts_toward_revenue' => $this->isPaidStatus($submission->payment_status),
            'label' => $submission->dynamicForm?->title ?: 'Form payment',
            'payer_name' => $user?->name ?? $submission->name,
            'payer_email' => $user?->email ?? $submission->email,
            'payer_phone' => $user?->phone ?? $submission->phone,
            'payment_provider' => strtolower((string) $submission->payment_provider),
            'gateway' => strtolower((string) $submission->payment_provider),
            'status' => (string) $submission->payment_status,
            'currency' => strtoupper((string) ($submission->currency ?: 'GBP')),
            'amount' => (float) $submission->amount,
            'initiated_at' => $submission->created_at,
            'settled_at' => $submission->paid_at,
            'occurred_at' => $occurredAt,
            ...$this->ipFields($metadata),
            'metadata' => [
                'dynamic_form_id' => $submission->dynamic_form_id,
                'submission_reference' => $submission->reference,
                'submission_status' => $submission->status,
                'submission_metadata' => $metadata,
            ],
        ]);
    }

    public function syncFundraisingContribution(CampaignContribution $contribution): GoshenTransactionEntry
    {
        $contribution->loadMissing(['campaign', 'user']);

        $metadata = $this->metadataArray($contribution->metadata);
        $user = $contribution->user instanceof MobileUser ? $contribution->user : null;
        $occurredAt = $contribution->succeeded_at ?? $contribution->created_at ?? now();

        return $this->upsert($contribution, [
            'mobile_user_id' => $user?->id,
            'source' => 'fundraising',
            'source_reference' => $contribution->provider_reference ?: $contribution->wallet_transaction_id,
            'transaction_kind' => 'payment',
            'direction' => 'credit',
            'counts_toward_revenue' => $this->isPaidStatus($contribution->status),
            'label' => $contribution->campaign?->title ?: 'Fundraising contribution',
            'payer_name' => $user?->name ?? $contribution->display_name,
            'payer_email' => $user?->email,
            'payer_phone' => $user?->phone,
            'payment_provider' => strtolower((string) ($contribution->payment_provider ?? 'wallet')),
            'gateway' => strtolower((string) ($contribution->payment_provider ?? 'wallet')),
            'status' => (string) $contribution->status,
            'currency' => strtoupper((string) $contribution->currency),
            'amount' => (float) $contribution->amount,
            'initiated_at' => $contribution->created_at,
            'settled_at' => $contribution->succeeded_at,
            'occurred_at' => $occurredAt,
            ...$this->ipFields($metadata),
            'metadata' => [
                'campaign_id' => $contribution->campaign_id,
                'wallet_transaction_id' => $contribution->wallet_transaction_id,
                'contribution_metadata' => $metadata,
            ],
        ]);
    }

    private function upsert(Model $source, array $attributes): GoshenTransactionEntry
    {
        return GoshenTransactionEntry::query()->updateOrCreate(
            [
                'source_table' => $source->getTable(),
                'source_id' => $source->getKey(),
            ],
            $attributes,
        );
    }

    private function metadataArray(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function ipFields(array $metadata): array
    {
        $rawIp = collect([
            Arr::get($metadata, 'payer_ip'),
            Arr::get($metadata, 'ip_address'),
            Arr::get($metadata, 'client_ip'),
            Arr::get($metadata, 'request_ip'),
            Arr::get($metadata, 'created_ip'),
        ])->first(fn ($value): bool => is_string($value) && trim($value) !== '');

        $rawAgent = collect([
            Arr::get($metadata, 'user_agent'),
            Arr::get($metadata, 'payer_user_agent'),
            Arr::get($metadata, 'request_user_agent'),
        ])->first(fn ($value): bool => is_string($value) && trim($value) !== '');

        return [
            'payer_ip_hash' => $rawIp ? $this->hashSensitiveValue((string) $rawIp) : null,
            'payer_user_agent_hash' => $rawAgent ? $this->hashSensitiveValue((string) $rawAgent) : null,
            'payer_ip_label' => $rawIp ? 'Captured' : 'Not captured',
        ];
    }

    private function hashSensitiveValue(string $value): string
    {
        return hash_hmac('sha256', trim($value), (string) config('app.key'));
    }

    private function walletDirection(string $type): string
    {
        return in_array($type, [
            'top_up',
            'scheduled_top_up',
            'voucher_top_up',
            'admin_top_up',
            'refund',
            'withdrawal_refund',
            'transfer_in',
            'referral_conversion',
        ], true) ? 'credit' : 'debit';
    }

    private function walletLabel(string $type): string
    {
        return match ($type) {
            'top_up' => 'Wallet top-up',
            'scheduled_top_up' => 'Automatic wallet top-up',
            'voucher_top_up' => 'Voucher wallet top-up',
            'admin_top_up' => 'Admin wallet top-up',
            'retreat_payment' => 'Goshen Retreat wallet payment',
            'wallet_payment' => 'Wallet payment',
            'giving_payment' => 'Giving from wallet',
            'fundraising_payment' => 'Fundraising contribution from wallet',
            'withdrawal_request' => 'Wallet withdrawal request',
            'withdrawal_refund' => 'Wallet withdrawal refund',
            'transfer_in' => 'Wallet transfer received',
            'transfer_out' => 'Wallet transfer sent',
            'referral_conversion' => 'Referral points conversion',
            default => str($type)->replace('_', ' ')->headline()->toString(),
        };
    }

    private function isPaidStatus(mixed $status): bool
    {
        return in_array(strtolower((string) $status), ['paid', 'succeeded', 'completed', 'success'], true);
    }
}
