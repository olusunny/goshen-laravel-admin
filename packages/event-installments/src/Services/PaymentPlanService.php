<?php

namespace Personal\EventInstallments\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentPlan;

class PaymentPlanService
{
    public function createInstallments(Booking $booking, PaymentPlan $plan, ?CarbonInterface $startDate = null): void
    {
        if ((float) $booking->total <= 0) {
            throw new InvalidArgumentException('Booking total must be greater than zero.');
        }

        $startDate ??= now();

        DB::transaction(function () use ($booking, $plan, $startDate) {
            $booking->installments()->delete();

            $amounts = $this->calculateAmounts((float) $booking->total, $plan);

            foreach ($amounts as $index => $amount) {
                PaymentInstallment::query()->create([
                    'booking_id' => $booking->id,
                    'sequence' => $index + 1,
                    'currency' => $booking->currency,
                    'amount' => $amount,
                    'paid_amount' => 0,
                    'due_on' => $startDate->copy()->addDays($index * (int) $plan->interval_days)->toDateString(),
                    'status' => InstallmentStatus::Pending,
                ]);
            }
        });
    }

    /**
     * @return array<int, float>
     */
    public function calculateAmounts(float $total, PaymentPlan $plan): array
    {
        $count = max(1, (int) $plan->installment_count);
        $deposit = $this->calculateDeposit($total, $plan);
        if ($deposit > $total) {
            throw new InvalidArgumentException('The payment plan deposit cannot exceed the ticket total.');
        }

        if ($count === 1) {
            return [$this->roundMoney($total)];
        }

        $remaining = $this->roundMoney($total - $deposit);
        $standard = $this->roundMoney($remaining / ($count - 1));
        $amounts = [$deposit];

        for ($i = 2; $i <= $count; $i++) {
            $amounts[] = $standard;
        }

        $difference = $this->roundMoney($total - array_sum($amounts));
        $amounts[$count - 1] = $this->roundMoney($amounts[$count - 1] + $difference);

        return $amounts;
    }

    private function calculateDeposit(float $total, PaymentPlan $plan): float
    {
        $value = (float) $plan->deposit_value;

        if ($plan->deposit_type === 'fixed') {
            if ($value > $total) {
                throw new InvalidArgumentException('Fixed deposit cannot exceed the ticket total.');
            }

            return $this->roundMoney($value);
        }

        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException('Percentage deposit must be between 0 and 100.');
        }

        return $this->roundMoney($total * ($value / 100));
    }

    private function roundMoney(float $amount): float
    {
        return round($amount, 2);
    }
}
