<?php

namespace ChurchTools\DigitalCounseling\Console;

use Spatie\Permission\Models\Permission;
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

            foreach ((array) config('counseling.permissions', []) as $permission) {
                if (is_string($permission) && $permission !== '') {
                    Permission::findOrCreate($permission, 'web');
                    Permission::findOrCreate($permission, 'mobile');
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
            // Permission installation should not make package installation unrecoverable.
        }
    }
}
