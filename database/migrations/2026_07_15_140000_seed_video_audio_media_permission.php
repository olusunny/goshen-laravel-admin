<?php

use App\Filament\Resources\MediaItemResource;
use App\Filament\Resources\VideoAudioMediaResource;
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

        $permission = Permission::findOrCreate(
            AdminPermissions::resourcePermission(VideoAudioMediaResource::class),
            'web',
        );

        Role::query()
            ->where('guard_name', 'web')
            ->where(function ($query): void {
                $query
                    ->where('name', 'super_admin')
                    ->orWhereHas('permissions', fn ($permissions) => $permissions->whereIn('name', [
                        AdminPermissions::resourcePermission(MediaItemResource::class),
                        'manage_media',
                    ]));
            })
            ->get()
            ->each(fn (Role $role): Role => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', AdminPermissions::resourcePermission(VideoAudioMediaResource::class))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
