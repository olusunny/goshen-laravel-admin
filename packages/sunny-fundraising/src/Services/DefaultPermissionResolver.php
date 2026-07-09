<?php

namespace Sunny\Fundraising\Services;

use Sunny\Fundraising\Contracts\PermissionResolverContract;

class DefaultPermissionResolver implements PermissionResolverContract
{
    public function canManage(mixed $user): bool
    {
        return (bool) ($user?->can('fundraising.manage') ?? false)
            || (bool) ($user?->hasRole('super_admin') ?? false);
    }

    public function canContribute(mixed $user): bool
    {
        if (! $user) {
            return false;
        }

        return method_exists($user, 'canUseCommunity') ? $user->canUseCommunity() : true;
    }
}
