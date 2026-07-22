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

        $permissions = Permission::query()
            ->where('guard_name', 'mobile')
            ->where('name', 'like', 'prayer_session_attendance.%')
            ->pluck('name')
            ->all();

        Role::query()
            ->where('guard_name', 'mobile')
            ->whereIn('name', ['event_manager', 'Event Manager', 'Triumphant IT Manager'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
