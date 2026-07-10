<?php

namespace Tests\Feature;

use App\Services\GoshenSingleFullPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentPlan;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\PaymentSettlementService;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class GoshenSingleFullPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_canonical_record_for_the_complete_booking_total(): void
    {
        $booking = $this->booking(total: 250);

        $record = app(GoshenSingleFullPaymentService::class)->createForBooking($booking);

        $this->assertSame(1, $booking->installments()->count());
        $this->assertSame(1, (int) $record->sequence);
        $this->assertSame('NGN', $record->currency);
        $this->assertSame('250.00', $record->amount);
        $this->assertSame('0.00', $record->paid_amount);
        $this->assertSame(InstallmentStatus::Pending, $record->status);
        $this->assertNull($booking->fresh()->payment_plan_id);

        $this->assertSame($record->id, app(GoshenSingleFullPaymentService::class)->createForBooking($booking)->id);
        $this->assertSame(1, $booking->installments()->count());
    }

    /**
     * @dataProvider invalidCanonicalRecordProvider
     */
    public function test_it_rejects_plan_partial_multi_row_and_mismatched_payment_state(string $invalidState): void
    {
        $booking = $this->booking(total: 250);
        $record = $this->paymentRecord($booking, amount: 250);

        match ($invalidState) {
            'plan' => $this->attachPlan($booking),
            'partial_booking' => $booking->forceFill(['paid_total' => 10])->save(),
            'partial_record' => $record->forceFill(['paid_amount' => 10])->save(),
            'wrong_amount' => $record->forceFill(['amount' => 200])->save(),
            'wrong_currency' => $record->forceFill(['currency' => 'GBP'])->save(),
            'wrong_sequence' => $record->forceFill(['sequence' => 2])->save(),
            'multiple' => $this->paymentRecord($booking, amount: 125, sequence: 2),
        };

        $this->expectException(RuntimeException::class);
        app(GoshenSingleFullPaymentService::class)->assertPayable($booking->fresh(), $record->fresh());
    }

    public static function invalidCanonicalRecordProvider(): array
    {
        return collect(['plan', 'partial_booking', 'partial_record', 'wrong_amount', 'wrong_currency', 'wrong_sequence', 'multiple'])
            ->mapWithKeys(fn (string $state): array => [$state => [$state]])
            ->all();
    }

    public function test_route_flags_are_literal_false_even_when_environment_requests_plan_routes(): void
    {
        putenv('EVENT_INSTALLMENTS_API_ROUTES_ENABLED=true');
        putenv('EVENT_INSTALLMENTS_ADMIN_ROUTES_ENABLED=true');

        $config = require base_path('config/event-installments.php');

        $this->assertFalse($config['api_routes_enabled']);
        $this->assertFalse($config['admin_routes_enabled']);
    }

    public function test_package_settlement_rejects_a_partial_transaction_without_changing_financial_state(): void
    {
        $booking = $this->booking(total: 250);
        $record = $this->paymentRecord($booking, amount: 250);
        $transaction = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'installment_id' => $record->id,
            'gateway' => 'stripe',
            'provider_reference' => 'partial_' . $booking->id,
            'currency' => 'NGN',
            'amount' => 100,
            'status' => 'pending',
        ]);

        try {
            app(PaymentSettlementService::class)->markPaid($transaction, 100, 'NGN');
            $this->fail('Expected partial settlement to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('complete payment', $exception->getMessage());
        }

        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertSame('0.00', $record->fresh()->paid_amount);
        $this->assertSame('0.00', $booking->fresh()->paid_total);
        $this->assertSame(BookingStatus::Pending, $booking->fresh()->status);
    }

    public function test_legacy_repair_consolidates_unpaid_rows_and_expires_pending_transactions(): void
    {
        $booking = $this->booking(total: 300);
        $plan = $this->attachPlan($booking);
        $first = $this->paymentRecord($booking, amount: 150);
        $second = $this->paymentRecord($booking, amount: 150, sequence: 2);
        $pending = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'installment_id' => $second->id,
            'gateway' => 'null',
            'provider_reference' => 'legacy_pending_' . $booking->id,
            'currency' => 'NGN',
            'amount' => 150,
            'status' => 'pending',
        ]);

        $this->runRepairMigrationTwice();

        $booking->refresh();
        $record = $booking->installments()->sole();
        $this->assertNull($booking->payment_plan_id);
        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame(BookingStatus::Pending, $booking->status);
        $this->assertSame('0.00', $booking->paid_total);
        $this->assertSame(1, (int) $record->sequence);
        $this->assertSame('300.00', $record->amount);
        $this->assertSame('expired', $pending->fresh()->status);
        $this->assertNull($pending->fresh()->installment_id);
        $this->assertFalse((bool) data_get($booking->metadata, 'legacy_payment_review_required', false));
        $this->assertSame(1, $booking->installments()->count());
    }

    public function test_legacy_repair_preserves_and_flags_completed_financial_history(): void
    {
        $booking = $this->booking(total: 300);
        $plan = $this->attachPlan($booking);
        $first = $this->paymentRecord($booking, amount: 150, paidAmount: 150, status: InstallmentStatus::Paid);
        $second = $this->paymentRecord($booking, amount: 150, sequence: 2);
        $paid = PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'installment_id' => $first->id,
            'gateway' => 'stripe',
            'provider_reference' => 'legacy_paid_' . $booking->id,
            'currency' => 'NGN',
            'amount' => 150,
            'status' => 'paid',
        ]);

        $this->runRepairMigrationTwice();

        $booking->refresh();
        $this->assertNull($booking->payment_plan_id);
        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame(2, $booking->installments()->count());
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame(150.0, (float) $first->fresh()->paid_amount);
        $this->assertTrue((bool) data_get($booking->metadata, 'legacy_payment_review_required'));
    }

    public function test_legacy_repair_keeps_cancelled_bookings_cancelled(): void
    {
        $booking = $this->booking(total: 300, status: BookingStatus::Cancelled);
        $this->paymentRecord($booking, amount: 150);
        $this->paymentRecord($booking, amount: 150, sequence: 2);

        $this->runRepairMigrationTwice();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(2, $booking->installments()->count());
        $this->assertSame(0, $booking->installments()->where('status', InstallmentStatus::Pending->value)->count());
    }

    private function event(): Event
    {
        return Event::query()->create([
            'name' => 'Goshen Test',
            'slug' => 'goshen-test-' . str()->random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);
    }

    private function booking(float $total, BookingStatus $status = BookingStatus::Pending): Booking
    {
        $event = $this->event();

        return Booking::query()->create([
            'event_id' => $event->id,
            'customer_email' => str()->random(8) . '@example.test',
            'currency' => 'NGN',
            'subtotal' => $total,
            'total' => $total,
            'paid_total' => 0,
            'status' => $status,
        ]);
    }

    private function paymentRecord(
        Booking $booking,
        float $amount,
        int $sequence = 1,
        float $paidAmount = 0,
        InstallmentStatus $status = InstallmentStatus::Pending,
    ): PaymentInstallment {
        return PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => $sequence,
            'currency' => $booking->currency,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'due_on' => now()->toDateString(),
            'paid_at' => $paidAmount > 0 ? now() : null,
            'status' => $status,
        ]);
    }

    private function attachPlan(Booking $booking): PaymentPlan
    {
        $plan = PaymentPlan::query()->create([
            'event_id' => $booking->event_id,
            'name' => 'Legacy plan',
            'currency' => 'NGN',
            'deposit_type' => 'percentage',
            'deposit_value' => 50,
            'installment_count' => 2,
            'interval_days' => 30,
            'grace_days' => 3,
            'ticket_issue_policy' => 'deposit_paid',
            'is_active' => true,
        ]);
        $booking->forceFill(['payment_plan_id' => $plan->id])->save();

        return $plan;
    }

    private function runRepairMigrationTwice(): void
    {
        $migration = require database_path('migrations/2026_07_10_162000_enforce_single_full_goshen_payments.php');
        $migration->up();
        $migration->up();
    }
}
