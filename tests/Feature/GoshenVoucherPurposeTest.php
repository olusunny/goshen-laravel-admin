<?php

namespace Tests\Feature;

use App\Models\GoshenVoucher;
use App\Models\GoshenVoucherUsage;
use App\Models\GoshenWallet;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\MobileUser;
use App\Services\GoshenVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use RuntimeException;
use Tests\TestCase;

class GoshenVoucherPurposeTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_vouchers_default_to_payment_purpose(): void
    {
        DB::table('goshen_vouchers')->insert([
            'code_hash' => hash('sha256', 'legacy-default-purpose'),
            'code_suffix' => 'LEGACY',
            'currency' => 'GBP',
            'amount' => 10,
            'max_uses' => 1,
            'used_count' => 0,
            'status' => GoshenVoucher::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(GoshenVoucher::PURPOSE_PAYMENTS, GoshenVoucher::query()->firstOrFail()->purpose);
    }

    public function test_wallet_funding_voucher_cannot_pay_for_a_booking(): void
    {
        [$booking, $installment, $member] = $this->bookingWithOutstandingPayment();
        $voucher = app(GoshenVoucherService::class)->createVoucher([
            'amount' => 100,
            'currency' => $booking->currency,
            'purpose' => GoshenVoucher::PURPOSE_WALLET_FUNDING,
        ]);

        try {
            app(GoshenVoucherService::class)->redeemForBooking(
                $booking,
                $installment,
                $voucher['code'],
                $member,
                $member,
            );

            $this->fail('Expected wallet-funding voucher to be rejected for booking payment.');
        } catch (RuntimeException $exception) {
            $this->assertSame('This voucher is only valid for wallet funding.', $exception->getMessage());
        }

        $this->assertVoucherAndBookingStateUnchanged($voucher['voucher'], $booking, $installment);
    }

    public function test_wallet_funding_voucher_is_rejected_by_payment_verification(): void
    {
        $voucher = app(GoshenVoucherService::class)->createVoucher([
            'amount' => 25,
            'currency' => 'GBP',
            'purpose' => GoshenVoucher::PURPOSE_WALLET_FUNDING,
        ]);

        $verification = app(GoshenVoucherService::class)->verify($voucher['code'], amount: 25, currency: 'GBP');

        $this->assertFalse($verification['valid']);
        $this->assertSame('This voucher is only valid for wallet funding.', $verification['message']);
        $this->assertSame(GoshenVoucher::PURPOSE_WALLET_FUNDING, $verification['voucher']['purpose']);
        $this->assertSame(0, GoshenVoucherUsage::query()->count());
    }

    public function test_payment_voucher_cannot_credit_a_wallet(): void
    {
        $member = $this->member();
        $wallet = $this->walletFor($member);
        $voucher = app(GoshenVoucherService::class)->createVoucher([
            'amount' => 25,
            'currency' => 'GBP',
            'purpose' => GoshenVoucher::PURPOSE_PAYMENTS,
        ]);

        try {
            app(GoshenVoucherService::class)->redeemForWalletTopUp($wallet, $voucher['code'], $member, $member);

            $this->fail('Expected payment voucher to be rejected for wallet funding.');
        } catch (RuntimeException $exception) {
            $this->assertSame('This voucher is only valid for payments.', $exception->getMessage());
        }

        $this->assertSame('0.00', $wallet->fresh()->balance);
        $this->assertSame(0, GoshenWalletLedgerEntry::query()->count());
        $this->assertSame(0, GoshenVoucherUsage::query()->count());
        $this->assertSame(0, $voucher['voucher']->fresh()->used_count);
        $this->assertSame(GoshenVoucher::STATUS_ACTIVE, $voucher['voucher']->fresh()->status);
    }

    public function test_invalid_voucher_purpose_is_rejected_before_creation(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Voucher purpose is not valid.');

        try {
            app(GoshenVoucherService::class)->createVoucher([
                'amount' => 10,
                'currency' => 'GBP',
                'purpose' => 'wallet',
            ]);
        } finally {
            $this->assertSame(0, GoshenVoucher::query()->count());
        }
    }

    private function assertVoucherAndBookingStateUnchanged(
        GoshenVoucher $voucher,
        Booking $booking,
        PaymentInstallment $installment,
    ): void {
        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertSame(GoshenVoucher::STATUS_ACTIVE, $voucher->fresh()->status);
        $this->assertSame('0.00', $booking->fresh()->paid_total);
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
        $this->assertSame('0.00', $installment->fresh()->paid_amount);
        $this->assertSame(InstallmentStatus::Pending, $installment->fresh()->status);
        $this->assertSame(0, PaymentTransaction::query()->count());
        $this->assertSame(0, GoshenVoucherUsage::query()->count());
    }

    /**
     * @return array{0: Booking, 1: PaymentInstallment, 2: MobileUser}
     */
    private function bookingWithOutstandingPayment(): array
    {
        $member = $this->member();
        [$event, $ticketType] = $this->publishedRetreatEvent(100);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'GBP',
            'subtotal' => 100,
            'total' => 100,
            'paid_total' => 0,
            'status' => BookingStatus::Pending,
        ]);

        Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Purpose',
            'last_name' => 'Member',
            'email' => $member->email,
            'phone' => $member->phone,
            'custom_fields' => [
                'title' => 'Mr.',
                'gender' => 'male',
                'marital_status' => 'Married',
                'age_group' => 'adult',
                'free_church_bus_interest' => 'no_thanks',
                'volunteer_department' => 'media',
            ],
        ]);

        $installment = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'GBP',
            'amount' => 100,
            'paid_amount' => 0,
            'due_on' => now()->addWeek()->toDateString(),
            'status' => InstallmentStatus::Pending,
        ]);

        PaymentTransaction::query()->where('booking_id', $booking->id)->delete();

        return [$booking->refresh(), $installment->refresh(), $member];
    }

    private function member(): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Purpose Member',
            'email' => 'purpose-member-'.str()->random(6).'@example.test',
            'phone' => '+447700900'.random_int(100, 999),
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function walletFor(MobileUser $member): GoshenWallet
    {
        return GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $member->id], [
            'currency' => 'GBP',
            'balance' => 0,
        ]);
    }

    /**
     * @return array{0: Event, 1: EventTicketType}
     */
    private function publishedRetreatEvent(float $price): array
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat Purpose',
            'slug' => 'goshen-retreat-purpose-'.str()->random(6),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => ['module' => 'goshen_retreat'],
        ]);

        EventSchedule::query()->create([
            'event_id' => $event->id,
            'day_number' => 1,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'metadata' => ['title' => 'Opening service'],
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'sku' => 'PURPOSE-'.str()->random(6),
            'currency' => 'GBP',
            'price' => $price,
            'min_per_booking' => 1,
            'max_per_booking' => 10,
            'is_active' => true,
        ]);

        return [$event->refresh(), $ticketType->refresh()];
    }
}
