<?php

namespace Sunny\Fundraising\Contracts;

use Sunny\Fundraising\Models\Campaign;

interface CampaignVisibilityContract
{
    public function visibleTo(?object $user, Campaign $campaign): bool;
}
