<?php

namespace Personal\EventInstallments\Services;

use InvalidArgumentException;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Services\Gateways\NullGateway;
use Personal\EventInstallments\Services\Gateways\PaystackGateway;
use Personal\EventInstallments\Services\Gateways\StripeGateway;

class PaymentGatewayManager
{
    public function default(): PaymentGateway
    {
        return $this->driver((string) config('event-installments.payments.default_gateway', 'null'));
    }

    public function driver(string $gateway): PaymentGateway
    {
        return match ($gateway) {
            'stripe' => new StripeGateway(),
            'paystack' => new PaystackGateway(),
            'null' => new NullGateway(),
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$gateway}]."),
        };
    }
}
