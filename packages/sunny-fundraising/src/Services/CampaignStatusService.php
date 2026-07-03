<?php

namespace Sunny\Fundraising\Services;

use Sunny\Fundraising\Models\Campaign;

class CampaignStatusService
{
    public function closeExpiredCampaigns(): int
    {
        return Campaign::query()
            ->where('status', Campaign::STATUS_ACTIVE)
            ->whereNotNull('end_at')
            ->where('end_at', '<=', now())
            ->update(['status' => Campaign::STATUS_CLOSED]);
    }
}
