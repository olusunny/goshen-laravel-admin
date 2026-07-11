<?php

namespace Tests\Feature;

use App\Models\GoshenVoucher;
use App\Models\GoshenVoucherUsage;
use App\Models\GoshenWallet;
use App\Models\GoshenWalletGoal;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\GoshenWalletSavingsPlan;
use App\Models\GoshenWalletWithdrawalRequest;
use App\Models\MobileUser;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Models\Ticket;
use Tests\TestCase;

class GoshenDemoWalletVoucherResetCommandTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRMATION_TOKEN = 'RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA';

    public function test_confirmation_token_is_required_and_leaves_demo_data_untouched(): void
    {
        $state = $this->demoFinancialState();

        $this->artisan('goshen:reset-demo-wallet-voucher-data')
            ->expectsOutput('Confirmation token required; no data was changed.')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame('125.50', $state['wallet']->fresh()->balance);
        $this->assertSame(1, GoshenVoucher::query()->count());
        $this->assertSame(1, GoshenVoucherUsage::query()->count());
        $this->assertSame(1, GoshenWalletLedgerEntry::query()->count());
        $this->assertSame(1, GoshenWalletGoal::query()->count());
        $this->assertSame(1, GoshenWalletSavingsPlan::query()->count());
        $this->assertSame(1, GoshenWalletWithdrawalRequest::query()->count());
    }

    public function test_confirmed_reset_deletes_demo_wallet_and_voucher_data_but_preserves_core_records(): void
    {
        $state = $this->demoFinancialState();

        $this->artisan('goshen:reset-demo-wallet-voucher-data', [
            '--confirm' => self::CONFIRMATION_TOKEN,
            '--dry-run' => true,
        ])
            ->expectsOutput('Dry run only; no data was changed.')
            ->assertExitCode(Command::SUCCESS);

        $this->assertSame('125.50', $state['wallet']->fresh()->balance);
        $this->assertSame(1, GoshenVoucher::query()->count());
        $this->assertSame(1, GoshenWalletLedgerEntry::query()->count());

        $this->artisan('goshen:reset-demo-wallet-voucher-data', [
            '--confirm' => self::CONFIRMATION_TOKEN,
        ])
            ->expectsOutput('Reset complete.')
            ->assertExitCode(Command::SUCCESS);

        $wallet = $state['wallet']->fresh();

        $this->assertSame('0.00', $wallet->balance);
        $this->assertNull($wallet->goal_amount);
        $this->assertNull($wallet->goal_label);
        $this->assertNull($wallet->goal_target_at);
        $this->assertSame('cus_demo_preserve', $wallet->stripe_customer_id);
        $this->assertSame('pm_demo_preserve', $wallet->stripe_payment_method_id);

        $this->assertSame(1, MobileUser::query()->count());
        $this->assertSame(1, GoshenWallet::query()->count());
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(1, PaymentTransaction::query()->count());
        $this->assertSame(1, PaymentInstallment::query()->count());

        $this->assertSame(0, GoshenVoucherUsage::query()->count());
        $this->assertSame(0, GoshenVoucher::query()->count());
        $this->assertSame(0, GoshenWalletWithdrawalRequest::query()->count());
        $this->assertSame(0, GoshenWalletSavingsPlan::query()->count());
        $this->assertSame(0, GoshenWalletGoal::query()->count());
        $this->assertSame(0, GoshenWalletLedgerEntry::query()->count());
    }

    /**
     * @return array{
     *     member: MobileUser,
     *     wallet: GoshenWallet,
     *     booking: Booking,
     *     ticket: Ticket,
     *     transaction: PaymentTransaction
     * }
     */
    private function demoFinancialState(): array
    {
        $member = MobileUser::query()->create([
            'name' => 'Demo Reset Member',
            'email' => 'demo-reset-member@example.test',
            'phone' => '+447700900001',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        $wallet = GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $member->id], [
            'currency' => 'GBP',
            'balance' => 125.50,
            'stripe_customer_id' => 'cus_demo_preserve',
            'stripe_payment_method_id' => 'pm_demo_preserve',
            'goal_amount' => 500,
            'goal_label' => 'Demo Goshen goal',
            'goal_target_at' => now()->addMonths(3),
        ]);

        $event = Event::query()->create([
            'name' => 'Demo Goshen Reset Event',
            'slug' => 'demo-goshen-reset-'.str()->random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Europe/London',
            'status' => 'published',
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Demo Ticket',
            'sku' => 'DEMO-RESET',
            'currency' => 'GBP',
            'price' => 125.50,
            'capacity' => 10,
            'min_per_booking' => 1,
            'max_per_booking' => 1,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'GBP',
            'subtotal' => 125.50,
            'total' => 125.50,
            'paid_total' => 125.50,
            'status' => BookingStatus::Paid,
        ]);

        $installment = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'GBP',
            'amount' => 125.50,
            'paid_amount' => 125.50,
            'due_on' => now()->toDateString(),
            'paid_at' => now(),
            'status' => InstallmentStatus::Paid,
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'DEMO-RESET-001',
            'formatted_number' => 'GSH-DEMO-RESET-001',
            'qr_hash' => hash('sha256', 'demo-reset-ticket'),
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
        ]);

        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'installment_id' => $installment->id,
            'gateway' => 'stripe',
            'provider_reference' => 'pi_demo_reset_preserve',
            'currency' => 'GBP',
            'amount' => 125.50,
            'status' => 'paid',
            'paid_at' => now(),
            'payload' => ['purpose' => 'preserve_payment_history'],
        ]);

        $ledgerEntry = GoshenWalletLedgerEntry::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'voucher_top_up',
            'status' => 'paid',
            'currency' => 'GBP',
            'amount' => 125.50,
            'gateway' => 'voucher',
            'provider_reference' => 'wallet_demo_reset_ledger',
            'metadata' => ['purpose' => 'demo_reset_fixture'],
            'settled_at' => now(),
        ]);

        GoshenWalletWithdrawalRequest::query()->create([
            'wallet_id' => $wallet->id,
            'mobile_user_id' => $member->id,
            'ledger_entry_id' => $ledgerEntry->id,
            'amount' => 25,
            'currency' => 'GBP',
            'status' => GoshenWalletWithdrawalRequest::STATUS_PENDING,
            'bank_name' => 'Demo Bank',
            'account_name' => 'Demo Reset Member',
            'account_number' => '12345678',
            'requested_at' => now(),
        ]);

        GoshenWalletSavingsPlan::query()->create([
            'wallet_id' => $wallet->id,
            'status' => 'active',
            'frequency' => 'weekly',
            'interval_count' => 1,
            'amount' => 10,
            'currency' => 'GBP',
            'remaining_cycles' => 3,
            'next_charge_at' => now()->addWeek(),
        ]);

        GoshenWalletGoal::query()->create([
            'wallet_id' => $wallet->id,
            'status' => GoshenWalletGoal::STATUS_ACTIVE,
            'label' => 'Demo goal',
            'currency' => 'GBP',
            'target_amount' => 500,
            'target_at' => now()->addMonths(3),
            'is_primary' => true,
        ]);

        $voucher = GoshenVoucher::query()->create([
            'event_id' => $event->id,
            'created_by_mobile_user_id' => $member->id,
            'label' => 'Demo reset voucher',
            'batch_reference' => 'DEMO-RESET-BATCH',
            'code_hash' => hash('sha256', 'demo-reset-voucher'),
            'code_suffix' => 'RESET1',
            'currency' => 'GBP',
            'amount' => 125.50,
            'max_uses' => 1,
            'used_count' => 1,
            'status' => GoshenVoucher::STATUS_EXHAUSTED,
            'purpose' => GoshenVoucher::PURPOSE_WALLET_FUNDING,
            'metadata' => ['purpose' => 'demo_reset_fixture'],
        ]);

        GoshenVoucherUsage::query()->create([
            'voucher_id' => $voucher->id,
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'payment_installment_id' => $installment->id,
            'payment_transaction_id' => $transaction->id,
            'mobile_user_id' => $member->id,
            'redeemed_by_mobile_user_id' => $member->id,
            'code_suffix' => $voucher->code_suffix,
            'currency' => 'GBP',
            'amount' => 125.50,
            'source' => 'wallet_top_up',
            'status' => 'applied',
            'metadata' => ['purpose' => 'demo_reset_fixture'],
        ]);

        return [
            'member' => $member,
            'wallet' => $wallet,
            'booking' => $booking,
            'ticket' => $ticket,
            'transaction' => $transaction,
        ];
    }
}
