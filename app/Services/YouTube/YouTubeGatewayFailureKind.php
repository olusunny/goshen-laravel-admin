<?php

namespace App\Services\YouTube;

enum YouTubeGatewayFailureKind: string
{
    case DailyQuota = 'daily_quota';
    case RateLimited = 'rate_limited';
    case ReauthenticationRequired = 'reauthentication_required';
    case Transient = 'transient';
    case Permanent = 'permanent';
}
