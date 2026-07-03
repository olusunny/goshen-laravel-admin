<?php

namespace Sunny\Fundraising\Contracts;

interface PermissionResolverContract
{
    public function canManage(mixed $user): bool;

    public function canContribute(mixed $user): bool;
}
