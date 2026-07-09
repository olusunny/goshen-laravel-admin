<?php

use App\Filament\Resources\AddonResource;
use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate(AdminPermissions::resourcePermission(AddonResource::class), 'web');

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'super_admin')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->where('name', AdminPermissions::resourcePermission(AddonResource::class))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
