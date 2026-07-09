<?php

namespace Sunny\Fundraising\Services;

use Sunny\Fundraising\Contracts\UserDisplayContract;

class DefaultUserDisplay implements UserDisplayContract
{
    public function displayName(mixed $user): string
    {
        return trim((string) ($user->name ?? $user->email ?? 'Supporter'));
    }

    public function avatarUrl(mixed $user): ?string
    {
        return isset($user->avatar) ? (string) $user->avatar : null;
    }
}
