<?php

namespace Sunny\Fundraising\Services;

use Spatie\Permission\Guard;
use Sunny\Fundraising\Contracts\PermissionResolverContract;

class DefaultPermissionResolver implements PermissionResolverContract
{
    public function canManage(mixed $user): bool
    {
        return (bool) ($user?->can('fundraising.manage') ?? false)
            || ($user
                && method_exists($user, 'hasRole')
                && $user->hasRole('super_admin', Guard::getDefaultName($user)));
    }

    public function canContribute(mixed $user): bool
    {
        if (! $user) {
            return false;
        }

        return method_exists($user, 'canUseCommunity') ? $user->canUseCommunity() : true;
    }
}
