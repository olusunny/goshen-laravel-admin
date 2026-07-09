<?php

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

        foreach ([
            AdminPermissions::FUNDRAISING_VIEW,
            AdminPermissions::FUNDRAISING_MANAGE,
            AdminPermissions::FUNDRAISING_CONTRIBUTE,
            AdminPermissions::FUNDRAISING_MEDIA_MANAGE,
        ] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'super_admin')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([
                AdminPermissions::FUNDRAISING_VIEW,
                AdminPermissions::FUNDRAISING_MANAGE,
                AdminPermissions::FUNDRAISING_CONTRIBUTE,
                AdminPermissions::FUNDRAISING_MEDIA_MANAGE,
            ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                AdminPermissions::FUNDRAISING_VIEW,
                AdminPermissions::FUNDRAISING_MANAGE,
                AdminPermissions::FUNDRAISING_CONTRIBUTE,
                AdminPermissions::FUNDRAISING_MEDIA_MANAGE,
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
