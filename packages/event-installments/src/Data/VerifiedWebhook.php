<?php

namespace Personal\EventInstallments\Data;

class VerifiedWebhook
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly ?string $reference,
        public readonly ?string $status,
        public readonly ?string $currency,
        public readonly ?float $amount,
        public readonly array $payload,
    ) {
    }
}
