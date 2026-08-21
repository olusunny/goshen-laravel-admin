<?php

namespace App\Services\YouTube;

use RuntimeException;

class YouTubeGatewayException extends RuntimeException
{
    public function __construct(
        public readonly YouTubeGatewayFailureKind $kind,
        public readonly string $safeCode,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($safeCode);
    }
}
