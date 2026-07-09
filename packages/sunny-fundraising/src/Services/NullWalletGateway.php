<?php

namespace Sunny\Fundraising\Services;

use Sunny\Fundraising\Contracts\WalletGatewayContract;
use Sunny\Fundraising\DTOs\WalletDebitResult;
use Sunny\Fundraising\Exceptions\WalletGatewayNotConfiguredException;
use Sunny\Fundraising\Models\Campaign;

class NullWalletGateway implements WalletGatewayContract
{
    public function getBalance(mixed $user): int|float|string
    {
        throw new WalletGatewayNotConfiguredException('Fundraising wallet gateway is not configured.');
    }

    public function debitForFundraisingContribution(mixed $user, int|float|string $amount, Campaign $campaign, array $metadata = []): WalletDebitResult
    {
        throw new WalletGatewayNotConfiguredException('Fundraising wallet gateway is not configured.');
    }
}
