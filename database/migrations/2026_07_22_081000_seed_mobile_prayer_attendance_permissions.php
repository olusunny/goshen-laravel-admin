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

        $permissions = [
            'prayer_session_attendance.view',
            'prayer_session_attendance.confirm',
            'prayer_session_attendance.coordinate',
            'prayer_session_attendance.report',
            'prayer_session_attendance.correct',
            'prayer_session_attendance.admin',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'mobile');
        }

        Role::query()
            ->where('guard_name', 'mobile')
            ->where('name', 'super_admin')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
