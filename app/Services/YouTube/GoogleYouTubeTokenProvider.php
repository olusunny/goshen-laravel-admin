<?php

namespace App\Services\YouTube;

use App\Models\GoshenYouTubeConnection;
use Google\Auth\OAuth2;
use RuntimeException;

class GoogleYouTubeTokenProvider
{
    /**
     * @return array<string, mixed>
     */
    public function exchangeAuthorizationCode(string $code, string $codeVerifier): array
    {
        $token = $this->oauth([
            'code' => $code,
            'codeVerifier' => $codeVerifier,
        ])->fetchAuthToken();

        return $this->normalizeToken($token);
    }

    public function accessToken(GoshenYouTubeConnection $connection): string
    {
        $token = $connection->refresh_token_payload ?: [];

        if (blank($token['refresh_token'] ?? null)) {
            throw new YouTubeGatewayException(
                YouTubeGatewayFailureKind::ReauthenticationRequired,
                'youtube_refresh_token_missing',
            );
        }

        if (filled($token['access_token'] ?? null) && isset($token['expires_at']) && now()->addMinute()->lt($token['expires_at'])) {
            return (string) $token['access_token'];
        }

        $refreshed = $this->oauth(['refreshToken' => (string) $token['refresh_token']])->fetchAuthToken();
        $normalized = $this->normalizeToken($refreshed, (string) $token['refresh_token']);
        $connection->forceFill(['refresh_token_payload' => $normalized])->save();

        return (string) $normalized['access_token'];
    }

    public function authorizationUrl(string $state, string $codeVerifier): string
    {
        return (string) $this->oauth([
            'state' => $state,
            'codeVerifier' => $codeVerifier,
        ])->buildFullAuthorizationUri([
            'prompt' => 'consent',
            'include_granted_scopes' => 'false',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function oauth(array $extra = []): OAuth2
    {
        $clientId = config('goshen_experience.youtube.client_id');
        $clientSecret = config('goshen_experience.youtube.client_secret');

        if (blank($clientId) || blank($clientSecret)) {
            throw new RuntimeException('YouTube OAuth credentials are not configured.');
        }

        return new OAuth2(array_merge([
            'authorizationUri' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => config('goshen_experience.youtube.redirect_uri'),
            'scope' => config('goshen_experience.youtube.scopes'),
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $token
     * @return array<string, mixed>
     */
    private function normalizeToken(array $token, ?string $refreshToken = null): array
    {
        if (blank($token['access_token'] ?? null)) {
            $kind = app(YouTubeGatewayFailureClassifier::class)->classify($token);

            throw new YouTubeGatewayException($kind, 'youtube_token_exchange_failed');
        }

        $resolvedRefreshToken = $token['refresh_token'] ?? $refreshToken;
        if (blank($resolvedRefreshToken)) {
            throw new YouTubeGatewayException(
                YouTubeGatewayFailureKind::ReauthenticationRequired,
                'youtube_refresh_token_missing',
            );
        }

        return [
            'access_token' => (string) $token['access_token'],
            'refresh_token' => (string) $resolvedRefreshToken,
            'token_type' => (string) ($token['token_type'] ?? 'Bearer'),
            'expires_at' => now()->addSeconds(max(60, (int) ($token['expires_in'] ?? 3600) - 60))->toIso8601String(),
            'scope' => $token['scope'] ?? null,
        ];
    }
}
