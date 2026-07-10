<?php

namespace App\Services;

use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\PaymentInstallment;
use RuntimeException;

class GoshenSingleFullPaymentService
{
    public function createForBooking(Booking $booking): PaymentInstallment
    {
        $booking->refresh();

        if ((float) $booking->total <= 0) {
            throw new RuntimeException('A full-payment record requires a positive booking total.');
        }

        $existing = $booking->installments()->get();
        if ($existing->isNotEmpty()) {
            if ($existing->count() !== 1) {
                throw new RuntimeException('This registration does not have one complete payment record.');
            }

            $record = $existing->firstOrFail();
            $this->assertPayable($booking, $record);

            return $record;
        }

        if ($booking->payment_plan_id !== null) {
            throw new RuntimeException('Payment plans are not available for Goshen registrations.');
        }

        return PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => strtoupper((string) $booking->currency),
            'amount' => round((float) $booking->total, 2),
            'paid_amount' => 0,
            'due_on' => now()->toDateString(),
            'status' => InstallmentStatus::Pending,
            'metadata' => [
                'label' => 'Full registration payment',
                'single_full_payment' => true,
            ],
        ]);
    }

    public function assertPayable(Booking $booking, PaymentInstallment $record): void
    {
        $booking->refresh();

        if ($booking->payment_plan_id !== null) {
            throw new RuntimeException('Payment plans are not available for Goshen registrations.');
        }

        if ((bool) data_get($booking->metadata, 'legacy_payment_review_required', false)) {
            throw new RuntimeException('This registration requires a financial review before payment can continue.');
        }

        if ((int) $record->booking_id !== (int) $booking->id) {
            throw new RuntimeException('This payment record does not belong to the registration.');
        }

        $records = $booking->installments()->get();
        if ($records->count() !== 1 || (int) $records->first()?->id !== (int) $record->id) {
            throw new RuntimeException('This registration does not have one complete payment record.');
        }

        $status = $booking->status instanceof BookingStatus
            ? $booking->status
            : BookingStatus::tryFrom((string) $booking->status);
        if (in_array($status, [BookingStatus::Paid, BookingStatus::Cancelled, BookingStatus::Refunded], true)) {
            throw new RuntimeException('This registration is not open for payment.');
        }

        if ((float) $booking->total <= 0
            || (float) $booking->paid_total > 0.0001
            || (float) $record->paid_amount > 0.0001
            || (int) $record->sequence !== 1
            || abs((float) $record->amount - (float) $booking->total) > 0.009
            || strtoupper((string) $record->currency) !== strtoupper((string) $booking->currency)) {
            throw new RuntimeException('This registration must be paid once for the complete total.');
        }

        $recordStatus = $record->status instanceof InstallmentStatus
            ? $record->status
            : InstallmentStatus::tryFrom((string) $record->status);
        if (in_array($recordStatus, [InstallmentStatus::Paid, InstallmentStatus::Cancelled, InstallmentStatus::Refunded], true)) {
            throw new RuntimeException('This payment record is not open for payment.');
        }
    }
}
