<?php

namespace App\Filament\Resources\Concerns;

use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuthorizesResourceAccess
{
    public static function canViewAny(): bool
    {
        return static::adminCanManageResource();
    }

    public static function canCreate(): bool
    {
        return static::adminCanManageResource();
    }

    public static function canView(Model $record): bool
    {
        return static::adminCanManageResource();
    }

    public static function canEdit(Model $record): bool
    {
        return static::adminCanManageResource();
    }

    public static function canDelete(Model $record): bool
    {
        return static::adminCanManageResource();
    }

    public static function canDeleteAny(): bool
    {
        return static::adminCanManageResource();
    }

    protected static function adminCanManageResource(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can(AdminPermissions::resourcePermission(static::class));
    }
}
