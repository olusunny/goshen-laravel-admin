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

        $permissions = collect([
            'manage_goshen_vouchers',
            'manage_goshen_quiz',
            'manage_goshen_quizzes',
        ])->map(fn (string $name): Permission => Permission::findOrCreate($name, 'mobile'));

        Role::query()
            ->where('guard_name', 'mobile')
            ->get()
            ->filter(fn (Role $role): bool => in_array(
                str($role->name)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                [
                    'admin',
                    'superadmin',
                    'eventmanager',
                    'goshenmanager',
                    'retreatmanager',
                    'quizmanager',
                    'goshenquizmanager',
                ],
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
