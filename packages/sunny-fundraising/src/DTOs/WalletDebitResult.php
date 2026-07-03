<?php

namespace Sunny\Fundraising\DTOs;

class WalletDebitResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int|string|null $walletTransactionId = null,
        public readonly int|float|string|null $newBalance = null,
        public readonly ?string $currency = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $message = null,
    ) {}

    public static function success(int|string $walletTransactionId, int|float|string $newBalance, string $currency): self
    {
        return new self(true, $walletTransactionId, $newBalance, $currency);
    }

    public static function failure(string $message, ?string $errorCode = null): self
    {
        return new self(false, null, null, null, $errorCode, $message);
    }
}
