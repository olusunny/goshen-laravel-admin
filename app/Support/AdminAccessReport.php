<?php

namespace App\Support;

use App\Models\Addon;
use App\Models\AdminMenuRoleVisibility;
use App\Models\User;
use App\Services\Addons\AddonRuntimeLoader;

class AdminAccessReport
{
    public static function forUser(User $user): array
    {
        $user->load('roles.permissions', 'permissions');
        $catalog = AdminPermissions::all();
        $roles = $user->roles->where('guard_name', 'web');
        $effective = $user->getAllPermissions()->where('guard_name', 'web')->sortBy('name')
            ->map(fn ($permission): array => [
                'name' => $permission->name,
                'label' => $catalog[$permission->name] ?? $permission->name,
                'catalogued' => array_key_exists($permission->name, $catalog),
                'direct' => $user->permissions->contains('id', $permission->id),
                'roles' => $roles->filter(fn ($role): bool => $role->permissions->contains('id', $permission->id))->pluck('name')->all(),
            ])->values()->all();
        $grants = array_column($effective, 'name');
        $loader = app(AddonRuntimeLoader::class);
        $addons = Addon::query()->orderBy('package_key')->get(['package_key', 'name', 'status', 'manifest'])
            ->map(function (Addon $addon) use ($grants, $loader): array {
                $names = array_keys($loader->permissionLabelsForManifest(is_array($addon->manifest) ? $addon->manifest : []));

                return ['package_key' => $addon->package_key, 'name' => $addon->name, 'status' => $addon->status,
                    'granted_permissions' => array_values(array_intersect($names, $grants))];
            })->filter(fn (array $addon): bool => $addon['granted_permissions'] !== [])->values()->all();

        return [
            'user_id' => $user->id,
            'super_admin' => $roles->contains('name', 'super_admin'),
            'roles' => $roles->map->only(['id', 'name', 'guard_name'])->values()->all(),
            'permissions' => $effective,
            'addon_system_enabled' => (bool) config('addons.enabled', true),
            'addons' => $addons,
            'hidden_menus' => [],
            'note' => 'Permissions are additive. Role and individual grants determine both menu visibility and access. Legacy menu hides are ignored. Feature switches and enabled add-ons can also limit availability. Super Admin bypasses feature permission checks.',
        ];
    }
}
