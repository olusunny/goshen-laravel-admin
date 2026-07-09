<?php

namespace Personal\EventInstallments\Data;

class RefundResult
{
    public function __construct(
        public readonly string $gateway,
        public readonly string $reference,
        public readonly string $status,
        public readonly array $payload = [],
    ) {
    }
}
