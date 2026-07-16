<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources\Concerns;

use App\Support\AdminMenuRegistry;
use ChurchTools\DigitalCounseling\Contracts\PermissionResolverContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait AuthorizesCounselingAdmin
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::counselingAdminCanView()
            && AdminMenuRegistry::visibleForResource(static::class);
    }

    public static function canViewAny(): bool
    {
        return static::counselingAdminCanView();
    }

    public static function canCreate(): bool
    {
        return static::counselingAdminCanManage();
    }

    public static function canView(Model $record): bool
    {
        return static::counselingAdminCanView();
    }

    public static function canEdit(Model $record): bool
    {
        return static::counselingAdminCanManage();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function counselingAdminCanView(): bool
    {
        if (! static::counselingAddonEnabled()) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $permissions = app(PermissionResolverContract::class);

        return $permissions->canTriage($user)
            || $permissions->canAssign($user)
            || $permissions->canManageSafeguarding($user)
            || $permissions->canManageSettings($user);
    }

    protected static function counselingAdminCanManage(): bool
    {
        if (! static::counselingAddonEnabled()) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $permissions = app(PermissionResolverContract::class);

        return $permissions->canTriage($user)
            || $permissions->canAssign($user)
            || $permissions->canManageSettings($user);
    }

    protected static function counselingAddonEnabled(): bool
    {
        if (! filter_var(config('counseling.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            if (Schema::hasTable('app_settings')) {
                $enabled = DB::table('app_settings')
                    ->where('key', 'counseling_enabled')
                    ->value('value');

                if ($enabled !== null && ! filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
                    return false;
                }
            }

            if (! Schema::hasTable('addons')) {
                return app()->environment(['local', 'testing']);
            }

            return DB::table('addons')
                ->where('package_key', 'church-tools.digital-counseling')
                ->value('status') === 'active';
        } catch (Throwable) {
            return false;
        }
    }
}
