<?php

namespace Personal\EventInstallments\Tests\Unit;

use Personal\EventInstallments\Models\PaymentPlan;
use Personal\EventInstallments\Services\PaymentPlanService;
use Personal\EventInstallments\Tests\TestCase;

class PaymentPlanServiceTest extends TestCase
{
    public function test_it_splits_percentage_deposit_into_installments(): void
    {
        $plan = new PaymentPlan([
            'deposit_type' => 'percentage',
            'deposit_value' => 50,
            'installment_count' => 3,
        ]);

        $amounts = app(PaymentPlanService::class)->calculateAmounts(100, $plan);

        $this->assertSame([50.0, 25.0, 25.0], $amounts);
    }

    public function test_it_adjusts_rounding_on_last_installment(): void
    {
        $plan = new PaymentPlan([
            'deposit_type' => 'fixed',
            'deposit_value' => 10,
            'installment_count' => 4,
        ]);

        $amounts = app(PaymentPlanService::class)->calculateAmounts(100, $plan);

        $this->assertSame(100.0, round(array_sum($amounts), 2));
        $this->assertSame([10.0, 30.0, 30.0, 30.0], $amounts);
    }
}
