<?php

namespace App\Services\YouTube;

use Google\Auth\OAuth2;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;

class YouTubeOAuthStateService
{
    /**
     * @return array{state: string, code_verifier: string}
     */
    public function create(int $adminId, ?int $connectionId = null): array
    {
        $state = Str::random(80);
        $codeVerifier = (new OAuth2([]))->generateCodeVerifier();

        $payload = json_encode([
            'admin_id' => $adminId,
            'connection_id' => $connectionId,
            'code_verifier' => $codeVerifier,
        ], JSON_THROW_ON_ERROR);

        Cache::put(
            $this->key($state),
            Crypt::encryptString($payload),
            now()->addSeconds((int) config('goshen_experience.youtube.state_ttl_seconds')),
        );

        return [
            'state' => $state,
            'code_verifier' => $codeVerifier,
        ];
    }

    /**
     * @return array{admin_id: int, connection_id: int|null, code_verifier: string}
     */
    public function consume(string $state, int $adminId): array
    {
        if (! preg_match('/^[A-Za-z0-9]{80}$/', $state)) {
            throw new AuthorizationException('The YouTube authorization state is invalid.');
        }

        $encryptedPayload = Cache::pull($this->key($state));
        if (! is_string($encryptedPayload)) {
            throw new AuthorizationException('The YouTube authorization state has expired.');
        }

        try {
            $payload = json_decode(Crypt::decryptString($encryptedPayload), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw new AuthorizationException('The YouTube authorization state has expired.');
        }

        if (
            ! is_array($payload)
            || (int) ($payload['admin_id'] ?? 0) !== $adminId
            || ! is_string($payload['code_verifier'] ?? null)
            || blank($payload['code_verifier'])
        ) {
            throw new AuthorizationException('The YouTube authorization state has expired.');
        }

        return [
            'admin_id' => $adminId,
            'connection_id' => isset($payload['connection_id']) ? (int) $payload['connection_id'] : null,
            'code_verifier' => (string) ($payload['code_verifier'] ?? ''),
        ];
    }

    private function key(string $state): string
    {
        return 'goshen-youtube-oauth-state:'.$state;
    }
}
