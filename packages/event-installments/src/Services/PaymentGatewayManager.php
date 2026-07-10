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
        $gateway = strtolower(trim($gateway));

        if (in_array($gateway, ['stripe', 'paystack'], true)
            && ! in_array($gateway, $this->enabledExternalGateways(), true)) {
            throw new InvalidArgumentException("Payment gateway [{$gateway}] is disabled for this release.");
        }

        return match ($gateway) {
            'stripe' => new StripeGateway,
            'paystack' => new PaystackGateway,
            'null' => new NullGateway,
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$gateway}]."),
        };
    }

    /** @return array<int, string> */
    private function enabledExternalGateways(): array
    {
        $gateways = config('event-installments.payments.enabled_external_gateways', ['stripe']);

        return collect(is_array($gateways) ? $gateways : [])
            ->map(fn (mixed $gateway): string => strtolower(trim((string) $gateway)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
