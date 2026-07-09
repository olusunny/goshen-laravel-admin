<?php

namespace Sunny\Fundraising\Services;

class MoneyFormatter
{
    public function format(float $amount, string $currency): string
    {
        return strtoupper($currency).' '.number_format($amount, 2);
    }
}
