<?php

use App\Filament\Resources\GoshenQuizAttemptResource;
use App\Filament\Resources\GoshenQuizCelebrationMediaResource;
use App\Filament\Resources\GoshenQuizResource;
use App\Filament\Resources\GoshenQuizWinnerResource;
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

        $permissions = collect($this->resourceClasses())
            ->map(fn (string $resource): string => AdminPermissions::resourcePermission($resource))
            ->map(fn (string $name) => Permission::findOrCreate($name, 'web'))
            ->all();

        Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'super_admin')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', collect($this->resourceClasses())
                ->map(fn (string $resource): string => AdminPermissions::resourcePermission($resource))
                ->all())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function resourceClasses(): array
    {
        return [
            GoshenQuizResource::class,
            GoshenQuizAttemptResource::class,
            GoshenQuizWinnerResource::class,
            GoshenQuizCelebrationMediaResource::class,
        ];
    }
};
