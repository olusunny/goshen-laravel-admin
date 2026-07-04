<?php

namespace Sunny\Fundraising\Filament\Resources\Concerns;

use App\Support\AdminMenuRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Sunny\Fundraising\Contracts\PermissionResolverContract;
use Throwable;

trait AuthorizesFundraisingAdmin
{
    public static function shouldRegisterNavigation(): bool
    {
        return static::fundraisingAdminCanManage()
            && AdminMenuRegistry::visibleForResource(static::class);
    }

    public static function canViewAny(): bool
    {
        return static::fundraisingAdminCanManage();
    }

    public static function canCreate(): bool
    {
        return static::fundraisingAdminCanManage();
    }

    public static function canView(Model $record): bool
    {
        return static::fundraisingAdminCanManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::fundraisingAdminCanManage();
    }

    public static function canDelete(Model $record): bool
    {
        return static::fundraisingAdminCanManage();
    }

    public static function canDeleteAny(): bool
    {
        return static::fundraisingAdminCanManage();
    }

    protected static function fundraisingAdminCanManage(): bool
    {
        if (! static::fundraisingAddonEnabled()) {
            return false;
        }

        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return app(PermissionResolverContract::class)->canManage($user);
    }

    protected static function fundraisingAddonEnabled(): bool
    {
        if (! filter_var(config('fundraising.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            if (! Schema::hasTable('addons')) {
                return app()->environment(['local', 'testing']);
            }

            $status = DB::table('addons')
                ->where('package_key', 'sunny.fundraising')
                ->value('status');

            if ($status === null) {
                return app()->environment(['local', 'testing']);
            }

            return $status === 'active';
        } catch (Throwable) {
            return false;
        }
    }
}
