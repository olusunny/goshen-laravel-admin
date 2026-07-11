<?php

namespace Tests\Feature;

use App\Models\GoshenTransactionEntry;
use App\Models\GoshenVoucher;
use App\Models\GoshenVoucherUsage;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Services\GoshenTransactionEntrySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\PaymentTransaction;
use Tests\TestCase;

class GoshenTransactionEntrySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_payment_wallet_and_voucher_records_into_central_entries(): void
    {
        $member = $this->member();
        $payment = $this->paymentTransaction($member);
        $walletEntry = $this->walletEntry($member);
        $voucherUsage = $this->voucherUsage($member);

        $sync = app(GoshenTransactionEntrySyncService::class);
        $sync->syncPaymentTransaction($payment);
        $sync->syncWalletLedgerEntry($walletEntry);
        $sync->syncVoucherUsage($voucherUsage);

        $this->assertSame(3, GoshenTransactionEntry::query()->count());

        $this->assertDatabaseHas('goshen_transaction_entries', [
            'source_table' => 'ei_payment_transactions',
            'source_id' => $payment->id,
            'mobile_user_id' => $member->id,
            'source' => 'retreat_payment',
            'payment_provider' => 'stripe',
            'status' => 'paid',
            'counts_toward_revenue' => true,
        ]);

        $this->assertDatabaseHas('goshen_transaction_entries', [
            'source_table' => 'goshen_wallet_ledger_entries',
            'source_id' => $walletEntry->id,
            'source' => 'wallet_ledger',
            'payment_provider' => 'stripe',
            'direction' => 'credit',
            'payer_ip_label' => 'Captured',
        ]);

        $this->assertDatabaseHas('goshen_transaction_entries', [
            'source_table' => 'goshen_voucher_usages',
            'source_id' => $voucherUsage->id,
            'source' => 'voucher_usage',
            'payment_provider' => 'voucher',
            'counts_toward_revenue' => false,
        ]);

        $walletProjection = GoshenTransactionEntry::query()
            ->where('source_table', 'goshen_wallet_ledger_entries')
            ->firstOrFail();

        $this->assertNotNull($walletProjection->payer_ip_hash);
        $this->assertSame(64, strlen((string) $walletProjection->payer_ip_hash));
        $this->assertSame('July', $walletProjection->occurred_at->format('F'));
        $this->assertSame('2026', $walletProjection->occurred_at->format('Y'));
    }

    public function test_sync_is_idempotent_for_source_record_updates(): void
    {
        $member = $this->member();
        $payment = $this->paymentTransaction($member);
        $sync = app(GoshenTransactionEntrySyncService::class);

        $sync->syncPaymentTransaction($payment);
        $payment->forceFill(['status' => 'failed'])->save();
        $sync->syncPaymentTransaction($payment->fresh());

        $this->assertSame(1, GoshenTransactionEntry::query()->count());
        $this->assertSame('failed', GoshenTransactionEntry::query()->firstOrFail()->status);
    }

    private function member(): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Transaction Member',
            'email' => 'transaction-member@example.test',
            'phone' => '+447700900321',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function paymentTransaction(MobileUser $member): PaymentTransaction
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026-test',
            'type' => 'single',
            'status' => 'published',
            'timezone' => 'Europe/London',
        ]);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'GBP',
            'subtotal' => 300,
            'total' => 300,
            'paid_total' => 300,
            'status' => 'paid',
        ]);

        return PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => 'stripe',
            'provider_reference' => 'cs_test_transaction_sync',
            'currency' => 'GBP',
            'amount' => 300,
            'status' => 'paid',
            'paid_at' => now()->setDate(2026, 7, 11)->setTime(12, 30),
            'payload' => [
                'request_ip' => '203.0.113.10',
                'request_user_agent' => 'Feature test',
            ],
        ]);
    }

    private function walletEntry(MobileUser $member)
    {
        $wallet = GoshenWallet::query()->updateOrCreate(
            ['mobile_user_id' => $member->id],
            [
                'currency' => 'GBP',
                'balance' => 50,
            ],
        );

        return $wallet->ledgerEntries()->create([
            'type' => 'top_up',
            'status' => 'paid',
            'currency' => 'GBP',
            'amount' => 50,
            'gateway' => 'stripe',
            'provider_reference' => 'gw_sync_test',
            'metadata' => [
                'request_ip' => '203.0.113.11',
                'request_user_agent' => 'Feature test wallet',
            ],
            'settled_at' => now()->setDate(2026, 7, 11)->setTime(13, 5),
        ]);
    }

    private function voucherUsage(MobileUser $member): GoshenVoucherUsage
    {
        $voucher = GoshenVoucher::query()->create([
            'label' => 'Sync voucher',
            'purpose' => GoshenVoucher::PURPOSE_PAYMENTS,
            'code_hash' => hash('sha256', 'sync-voucher'),
            'code_suffix' => 'SYNC01',
            'currency' => 'GBP',
            'amount' => 25,
            'max_uses' => 1,
            'used_count' => 1,
            'status' => GoshenVoucher::STATUS_EXHAUSTED,
        ]);

        return GoshenVoucherUsage::query()->create([
            'voucher_id' => $voucher->id,
            'mobile_user_id' => $member->id,
            'code_suffix' => 'SYNC01',
            'currency' => 'GBP',
            'amount' => 25,
            'source' => 'mobile_registration',
            'status' => GoshenVoucherUsage::STATUS_APPLIED,
            'metadata' => [
                'request_ip' => '203.0.113.12',
                'request_user_agent' => 'Feature test voucher',
            ],
        ]);
    }
}
