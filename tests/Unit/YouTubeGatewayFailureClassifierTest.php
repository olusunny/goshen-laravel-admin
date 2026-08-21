<?php

namespace Tests\Unit;

use App\Services\YouTube\YouTubeGatewayFailureClassifier;
use App\Services\YouTube\YouTubeGatewayFailureKind;
use Tests\TestCase;

class YouTubeGatewayFailureClassifierTest extends TestCase
{
    public function test_it_classifies_google_daily_quota_errors_without_treating_them_as_a_failed_upload(): void
    {
        $kind = app(YouTubeGatewayFailureClassifier::class)->classify([
            'error' => [
                'code' => 403,
                'errors' => [['reason' => 'quotaExceeded']],
            ],
        ]);

        $this->assertSame(YouTubeGatewayFailureKind::DailyQuota, $kind);
    }

    public function test_it_keeps_short_term_rate_limits_separate_from_daily_quota(): void
    {
        $kind = app(YouTubeGatewayFailureClassifier::class)->classify([
            'error' => [
                'code' => 429,
                'errors' => [['reason' => 'rateLimitExceeded']],
            ],
        ]);

        $this->assertSame(YouTubeGatewayFailureKind::RateLimited, $kind);
    }

    public function test_it_classifies_revoked_grants_as_reauthentication_required(): void
    {
        $kind = app(YouTubeGatewayFailureClassifier::class)->classify([
            'error' => [
                'code' => 401,
                'errors' => [['reason' => 'invalidGrant']],
            ],
        ]);

        $this->assertSame(YouTubeGatewayFailureKind::ReauthenticationRequired, $kind);
    }
}
