<?php

use App\Support\AdminPermissions;
use App\Filament\Resources\GoshenExperienceVideoResource;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect([
            AdminPermissions::TRIUMPHANT_EXPERIENCE_YOUTUBE,
            AdminPermissions::resourcePermission(GoshenExperienceVideoResource::class),
        ])->map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'));

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'super_admin')
            ->get()
            ->each(function (Role $role) use ($permissions): void {
                $permissions->each(fn (Permission $permission) => $role->givePermissionTo($permission));
            });
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [
                AdminPermissions::TRIUMPHANT_EXPERIENCE_YOUTUBE,
                AdminPermissions::resourcePermission(GoshenExperienceVideoResource::class),
            ])
            ->delete();
    }
};
