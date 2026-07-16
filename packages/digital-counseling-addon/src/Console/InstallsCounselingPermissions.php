<?php

namespace ChurchTools\DigitalCounseling\Console;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

trait InstallsCounselingPermissions
{
    private function installCounselingPermissions(): void
    {
        if (! class_exists(Permission::class)) {
            return;
        }

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $permissions = array_values(array_filter(
                (array) config('counseling.permissions', []),
                fn (mixed $permission): bool => is_string($permission) && $permission !== '',
            ));

            foreach ($permissions as $permission) {
                if (is_string($permission) && $permission !== '') {
                    Permission::findOrCreate($permission, 'web');
                    Permission::findOrCreate($permission, 'mobile');
                }
            }

            Role::query()
                ->where('guard_name', 'web')
                ->where('name', 'super_admin')
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permissions));

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
            // Permission installation should not make package installation unrecoverable.
        }
    }
}
