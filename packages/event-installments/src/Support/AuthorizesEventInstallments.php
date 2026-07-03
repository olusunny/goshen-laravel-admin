<?php

namespace Personal\EventInstallments\Support;

use Personal\EventInstallments\Models\Event;
use Throwable;

trait AuthorizesEventInstallments
{
    protected function hasRole($user, string $role): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
            return true;
        }

        if (isset($user->role) && $user->role === $role) {
            return true;
        }

        $roles = $user->roles ?? null;

        if (is_iterable($roles)) {
            foreach ($roles as $userRole) {
                if ((string) (is_object($userRole) ? ($userRole->name ?? $userRole->slug ?? '') : $userRole) === $role) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function hasPermission($user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'can')) {
            try {
                if ($user->can($permission)) {
                    return true;
                }
            } catch (Throwable) {
                return false;
            }
        }

        if (method_exists($user, 'hasPermissionTo')) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function isSuperAdmin($user): bool
    {
        return $this->hasRole($user, config('event-installments.roles.super_admin', 'super-admin'))
            || $this->hasPermission($user, 'event-installments.*');
    }

    protected function managesEvent($user, ?Event $event = null): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if ($this->hasRole($user, config('event-installments.roles.event_manager', 'event-manager'))) {
            return true;
        }

        if ($event && $event->owner_id && (string) $event->owner_id === (string) $user?->getAuthIdentifier()) {
            return true;
        }

        return $this->hasPermission($user, 'event-installments.events.manage');
    }

    protected function checksIn($user): bool
    {
        return $this->isSuperAdmin($user)
            || $this->hasRole($user, config('event-installments.roles.check_in_staff', 'check-in-staff'))
            || $this->hasPermission($user, 'event-installments.check-ins.manage');
    }

    protected function managesFinance($user): bool
    {
        return $this->isSuperAdmin($user)
            || $this->hasRole($user, config('event-installments.roles.finance_manager', 'finance-manager'))
            || $this->hasPermission($user, 'event-installments.payments.manage');
    }
}
