<?php

namespace Sunny\Fundraising\Contracts;

use Sunny\Fundraising\DTOs\WalletDebitResult;
use Sunny\Fundraising\Models\Campaign;

interface WalletGatewayContract
{
    public function getBalance(mixed $user): int|float|string;

    public function debitForFundraisingContribution(
        mixed $user,
        int|float|string $amount,
        Campaign $campaign,
        array $metadata = [],
    ): WalletDebitResult;
}
