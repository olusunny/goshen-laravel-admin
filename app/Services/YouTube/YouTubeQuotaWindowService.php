<?php

namespace App\Services\YouTube;

use App\Models\GoshenYouTubeConnection;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class YouTubeQuotaWindowService
{
    public const PACIFIC_TIMEZONE = 'America/Los_Angeles';

    public function nextPacificMidnight(DateTimeInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now)
            ->setTimezone(self::PACIFIC_TIMEZONE)
            ->startOfDay()
            ->addDay()
            ->startOfDay()
            ->utc();
    }

    public function deferConnection(GoshenYouTubeConnection $connection, ?DateTimeInterface $now = null): CarbonImmutable
    {
        $resumeAt = $this->nextPacificMidnight($now ?? now());

        $connection->forceFill([
            'health' => GoshenYouTubeConnection::HEALTH_QUOTA_BLOCKED,
            'quota_blocked_at' => $now ?? now(),
            'quota_resume_at' => $resumeAt,
            'quota_error_code' => 'quota_exceeded',
        ])->save();

        return $resumeAt;
    }

    public function circuitIsOpen(GoshenYouTubeConnection $connection, ?DateTimeInterface $now = null): bool
    {
        if ($connection->quota_resume_at === null) {
            return false;
        }

        return $connection->quota_resume_at->gt($now ?? now());
    }

    public function markResumed(GoshenYouTubeConnection $connection): void
    {
        $connection->forceFill([
            'health' => GoshenYouTubeConnection::HEALTH_HEALTHY,
            'quota_resume_at' => null,
            'quota_error_code' => null,
            'quota_resumed_at' => now(),
        ])->save();
    }
}
