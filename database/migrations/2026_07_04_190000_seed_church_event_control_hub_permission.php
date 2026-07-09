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

        $permission = Permission::findOrCreate('manage_church_events', 'mobile');
        Permission::findOrCreate('manage_church_events', 'web');

        Role::query()
            ->where('guard_name', 'mobile')
            ->get()
            ->filter(fn (Role $role): bool => in_array(
                str($role->name)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'eventsmanager', 'churcheventmanager', 'contentmanager', 'goshenmanager', 'retreatmanager', 'triumphantitmanager'],
                true,
            ))
            ->each(fn (Role $role): Role => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
