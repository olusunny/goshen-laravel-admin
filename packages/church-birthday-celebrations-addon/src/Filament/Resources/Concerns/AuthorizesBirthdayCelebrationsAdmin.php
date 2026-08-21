<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\Concerns;

use App\Support\AdminMenuRegistry;
use ChurchTools\ChurchBirthdayCelebrations\Services\AddonAvailability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuthorizesBirthdayCelebrationsAdmin
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::birthdayAdminCan(static::birthdayPermission())
            && AdminMenuRegistry::visibleForResource(static::class);
    }

    public static function canViewAny(): bool
    {
        return static::birthdayAdminCan(static::birthdayPermission());
    }

    public static function canCreate(): bool
    {
        return static::birthdayAdminCan(static::birthdayPermission());
    }

    public static function canView(Model $record): bool
    {
        return static::birthdayAdminCan(static::birthdayPermission());
    }

    public static function canEdit(Model $record): bool
    {
        return static::birthdayAdminCan(static::birthdayPermission());
    }

    public static function canDelete(Model $record): bool
    {
        return static::birthdayAdminCan(static::birthdayPermission());
    }

    public static function canDeleteAny(): bool
    {
        return static::birthdayAdminCan(static::birthdayPermission());
    }

    public static function canManageBirthdayCelebrations(): bool
    {
        return static::birthdayAdminCan('manage');
    }

    protected static function birthdayPermission(): string
    {
        return 'manage';
    }

    protected static function birthdayAdminCan(string $permission): bool
    {
        if (! app(AddonAvailability::class)->isActive()) {
            return false;
        }

        $user = Auth::user();

        return $user !== null
            && ($user->can('church_birthday_celebrations.'.$permission)
                || (method_exists($user, 'hasRole') && $user->hasRole('super_admin', 'web')));
    }
}
