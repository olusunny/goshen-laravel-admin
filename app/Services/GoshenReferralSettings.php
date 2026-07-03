<?php

namespace App\Services;

use App\Models\AppSetting;

class GoshenReferralSettings
{
    public function enabled(): bool
    {
        return filter_var(AppSetting::value('goshen_referrals_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    public function pointsPerPaidRegistration(): int
    {
        return max(1, (int) AppSetting::value('goshen_referral_points_per_paid_registration', 1));
    }

    public function walletAmountPerPoint(): float
    {
        return max(0, round((float) AppSetting::value('goshen_referral_wallet_amount_per_point', 0), 2));
    }

    public function minConvertiblePoints(): int
    {
        return max(1, (int) AppSetting::value('goshen_referral_min_convertible_points', 1));
    }

    public function payload(): array
    {
        return [
            'enabled' => $this->enabled(),
            'points_per_paid_registration' => $this->pointsPerPaidRegistration(),
            'wallet_amount_per_point' => $this->walletAmountPerPoint(),
            'min_convertible_points' => $this->minConvertiblePoints(),
        ];
    }
}
