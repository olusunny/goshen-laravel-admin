<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        if (! class_exists(Permission::class)) return;
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = array_values(array_filter(config('church-birthday-celebrations.permissions', [])));
        foreach (['web', 'mobile'] as $guard) {
            foreach ($permissions as $permission) Permission::findOrCreate($permission, $guard);
            Role::query()->where('guard_name', $guard)->where('name', 'super_admin')->get()->each(fn (Role $role) => $role->givePermissionTo($permissions));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void {}
};
