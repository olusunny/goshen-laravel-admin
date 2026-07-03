<?php

namespace App\Services;

use App\Models\CommunityPrayerRequest;
use App\Models\MobileUser;
use Carbon\CarbonImmutable;

class CommunityPrayerQuotaService
{
    public function availability(MobileUser $user): array
    {
        $last = CommunityPrayerRequest::query()
            ->where('mobile_user_id', $user->id)
            ->latest('created_at')
            ->first();

        $next = $last?->created_at
            ? CarbonImmutable::instance($last->created_at)->addDay()
            : null;
        $remaining = $next && $next->isFuture()
            ? now()->diffInSeconds($next, false)
            : 0;

        return [
            'can_submit_prayer' => $remaining <= 0,
            'last_prayer_at' => $last?->created_at?->toIso8601String(),
            'next_available_at' => $next?->toIso8601String(),
            'cooldown_seconds' => max(0, (int) $remaining),
            'message' => $remaining > 0
                ? 'You can submit one prayer request every 24 hours.'
                : null,
        ];
    }
}
