<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = collect([
            'manage_mobile_users',
            'create_mobile_users',
            'update_mobile_users',
            'delete_mobile_users',
        ]);

        $permissions = $names->map(fn (string $name): Permission => Permission::findOrCreate($name, 'mobile'));
        $names->each(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        Role::query()
            ->where('guard_name', 'mobile')
            ->get()
            ->filter(fn (Role $role): bool => in_array(
                str($role->name)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'triumphantitmanager'],
                true,
            ))
            ->each(fn (Role $role): Role => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
