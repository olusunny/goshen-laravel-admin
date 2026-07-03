<?php

namespace Sunny\Fundraising\Contracts;

interface UserDisplayContract
{
    public function displayName(mixed $user): string;

    public function avatarUrl(mixed $user): ?string;
}
