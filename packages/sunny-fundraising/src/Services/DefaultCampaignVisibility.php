<?php

namespace Sunny\Fundraising\Services;

use Sunny\Fundraising\Contracts\CampaignVisibilityContract;
use Sunny\Fundraising\Models\Campaign;

class DefaultCampaignVisibility implements CampaignVisibilityContract
{
    public function visibleTo(?object $user, Campaign $campaign): bool
    {
        return in_array($campaign->status, [Campaign::STATUS_ACTIVE, Campaign::STATUS_COMPLETED], true);
    }
}
