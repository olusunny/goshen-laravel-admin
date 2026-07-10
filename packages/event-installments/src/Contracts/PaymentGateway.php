<?php

namespace Personal\EventInstallments\Contracts;

use Illuminate\Http\Request;
use Personal\EventInstallments\Data\GatewayCheckout;
use Personal\EventInstallments\Data\RefundResult;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;

interface PaymentGateway
{
    public function createCheckout(PaymentInstallment $installment): GatewayCheckout;

    public function verifyWebhook(Request $request): VerifiedWebhook;

    public function refund(PaymentTransaction $transaction, float $amount, string $idempotencyKey): RefundResult;
}
