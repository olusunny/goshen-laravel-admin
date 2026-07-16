<?php

namespace ChurchTools\DigitalCounseling\Contracts;

use ChurchTools\DigitalCounseling\Models\CounselingCase;

interface PermissionResolverContract
{
    public function canRequest(mixed $user): bool;

    public function canTriage(mixed $user): bool;

    public function canAssign(mixed $user): bool;

    public function canRespondToCase(mixed $user, CounselingCase $case): bool;

    public function canViewCase(mixed $user, CounselingCase $case): bool;

    public function canManageSafeguarding(mixed $user): bool;

    public function canManageSettings(mixed $user): bool;

    public function canBreakGlass(mixed $user): bool;
}
