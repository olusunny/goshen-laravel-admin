<?php

namespace ChurchTools\DigitalCounseling\Services;

use ChurchTools\DigitalCounseling\Contracts\PermissionResolverContract;
use ChurchTools\DigitalCounseling\Models\CounselingCase;
use Throwable;

class DefaultPermissionResolver implements PermissionResolverContract
{
    public function canRequest(mixed $user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canUseCommunity')) {
            return (bool) $user->canUseCommunity();
        }

        return $this->hasPermission($user, $this->permission('request'));
    }

    public function canTriage(mixed $user): bool
    {
        return $this->hasPermission($user, $this->permission('triage'));
    }

    public function canAssign(mixed $user): bool
    {
        return $this->hasPermission($user, $this->permission('assign'));
    }

    public function canRespondToCase(mixed $user, CounselingCase $case): bool
    {
        if (! $user || $case->isClosed()) {
            return false;
        }

        return $this->isRequester($user, $case)
            || ($this->hasPermission($user, $this->permission('respond')) && $this->isAssigned($user, $case))
            || $this->canTriage($user);
    }

    public function canViewCase(mixed $user, CounselingCase $case): bool
    {
        if (! $user) {
            return false;
        }

        return $this->isRequester($user, $case)
            || $this->isAssigned($user, $case)
            || $this->canTriage($user)
            || $this->canManageSafeguarding($user)
            || $this->canBreakGlass($user);
    }

    public function canManageSafeguarding(mixed $user): bool
    {
        return $this->hasPermission($user, $this->permission('safeguarding'));
    }

    public function canManageSettings(mixed $user): bool
    {
        return $this->hasPermission($user, $this->permission('settings'));
    }

    public function canBreakGlass(mixed $user): bool
    {
        return $this->hasPermission($user, $this->permission('break_glass'));
    }

    private function isRequester(mixed $user, CounselingCase $case): bool
    {
        return $this->modelClass($user) === config('counseling.models.requester')
            && (int) $user->getKey() === (int) $case->requester_mobile_user_id;
    }

    private function isAssigned(mixed $user, CounselingCase $case): bool
    {
        $type = $this->modelClass($user);
        $id = $this->modelKey($user);

        if (! $type || ! $id) {
            return false;
        }

        return $case->assignments()
            ->whereNull('ended_at')
            ->where('assignee_type', $type)
            ->where('assignee_id', $id)
            ->exists();
    }

    private function hasPermission(mixed $user, string $permission): bool
    {
        if (! $user || $permission === '') {
            return false;
        }

        try {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }

            if (method_exists($user, 'can') && $user->can($permission)) {
                return true;
            }

            if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission)) {
                return true;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    private function permission(string $key): string
    {
        return (string) config("counseling.permissions.{$key}", '');
    }

    private function modelClass(mixed $user): ?string
    {
        return is_object($user) ? $user::class : null;
    }

    private function modelKey(mixed $user): ?int
    {
        if (! is_object($user) || ! method_exists($user, 'getKey')) {
            return null;
        }

        return (int) $user->getKey();
    }
}
