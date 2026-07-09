<?php

namespace ChurchTools\CloudBackup\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OAuthStateService
{
    private const CACHE_PREFIX = 'cloud-backup-oauth-state:';

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): string
    {
        $state = Str::random(48);
        $ttl = (int) config('cloud-backup.oauth.state_ttl_seconds', 600);

        Cache::put(self::CACHE_PREFIX.$state, $payload, now()->addSeconds($ttl));

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function consume(string $state): array
    {
        $key = self::CACHE_PREFIX.$state;
        $payload = Cache::pull($key);

        if (!is_array($payload)) {
            throw new \RuntimeException('OAuth state is invalid or expired.');
        }

        return $payload;
    }
}
