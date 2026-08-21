<?php

namespace Tests\Unit;

use App\Services\YouTube\YouTubeQuotaWindowService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class YouTubeQuotaWindowServiceTest extends TestCase
{
    public function test_it_uses_the_next_pacific_midnight_before_the_spring_dst_change(): void
    {
        $nextWindow = app(YouTubeQuotaWindowService::class)->nextPacificMidnight(
            CarbonImmutable::parse('2026-03-08 07:59:00', 'UTC'),
        );

        $this->assertSame('2026-03-08 08:00:00', $nextWindow->utc()->format('Y-m-d H:i:s'));
    }

    public function test_it_handles_the_short_day_after_the_spring_dst_change(): void
    {
        $nextWindow = app(YouTubeQuotaWindowService::class)->nextPacificMidnight(
            CarbonImmutable::parse('2026-03-08 08:00:00', 'UTC'),
        );

        $this->assertSame('2026-03-09 07:00:00', $nextWindow->utc()->format('Y-m-d H:i:s'));
    }

    public function test_it_handles_the_fall_dst_change_without_a_fixed_offset(): void
    {
        $nextWindow = app(YouTubeQuotaWindowService::class)->nextPacificMidnight(
            CarbonImmutable::parse('2026-11-01 07:00:00', 'UTC'),
        );

        $this->assertSame('2026-11-02 08:00:00', $nextWindow->utc()->format('Y-m-d H:i:s'));
    }
}
