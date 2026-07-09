<?php

namespace ChurchTools\CloudBackup\Services\Cloud\Concerns;

use ChurchTools\CloudBackup\Models\CloudBackupConnection;
use Illuminate\Support\Carbon;

trait ManagesTokens
{
    /**
     * @return array<string, mixed>
     */
    protected function validToken(CloudBackupConnection $connection): array
    {
        $token = $connection->token_payload ?: [];
        $expiresAt = isset($token['expires_at']) ? Carbon::parse($token['expires_at']) : null;

        if ($expiresAt && $expiresAt->subMinute()->isPast()) {
            $token = $this->refreshToken($token);
            $connection->forceFill([
                'token_payload' => $token,
                'last_error' => null,
            ])->save();
        }

        return $token;
    }

    protected function bearerHeaders(CloudBackupConnection $connection): array
    {
        $token = $this->validToken($connection);

        if (empty($token['access_token'])) {
            throw new \RuntimeException('Cloud connection is missing an access token.');
        }

        return [
            'Authorization' => 'Bearer '.$token['access_token'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function normalizeToken(array $payload, ?string $refreshToken = null): array
    {
        $expiresIn = (int) ($payload['expires_in'] ?? 3600);

        return [
            'access_token' => $payload['access_token'] ?? null,
            'refresh_token' => $payload['refresh_token'] ?? $refreshToken,
            'token_type' => $payload['token_type'] ?? 'Bearer',
            'expires_at' => now()->addSeconds(max($expiresIn - 60, 60))->toIso8601String(),
            'raw_scope' => $payload['scope'] ?? null,
        ];
    }
}
