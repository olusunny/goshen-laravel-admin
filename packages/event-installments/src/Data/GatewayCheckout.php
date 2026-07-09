<?php

namespace Personal\EventInstallments\Data;

class GatewayCheckout
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $reference,
        public readonly ?string $checkoutUrl = null,
        public readonly array $payload = [],
    ) {
    }
}
