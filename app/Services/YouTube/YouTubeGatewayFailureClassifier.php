<?php

namespace App\Services\YouTube;

class YouTubeGatewayFailureClassifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function classify(array $payload, ?int $status = null): YouTubeGatewayFailureKind
    {
        $errorValue = $payload['error'] ?? null;
        $error = is_array($errorValue) ? $errorValue : [];
        $status ??= is_numeric($error['code'] ?? null) ? (int) $error['code'] : null;
        $reasons = collect($error['errors'] ?? [])
            ->filter(fn (mixed $error): bool => is_array($error))
            ->pluck('reason')
            ->filter(fn (mixed $reason): bool => is_string($reason))
            ->map(fn (string $reason): string => strtolower($reason))
            ->all();

        if (is_string($errorValue)) {
            $reasons[] = strtolower(str_replace('_', '', $errorValue));
        }

        if (array_intersect($reasons, ['quotaexceeded', 'dailylimitexceeded'])) {
            return YouTubeGatewayFailureKind::DailyQuota;
        }

        if (array_intersect($reasons, ['invalidgrant', 'autherror', 'invalidcredentials', 'tokenexpired'])) {
            return YouTubeGatewayFailureKind::ReauthenticationRequired;
        }

        if ($status === 429 || array_intersect($reasons, ['ratelimitexceeded', 'userratelimitexceeded'])) {
            return YouTubeGatewayFailureKind::RateLimited;
        }

        if ($status !== null && $status >= 500) {
            return YouTubeGatewayFailureKind::Transient;
        }

        return YouTubeGatewayFailureKind::Permanent;
    }
}
