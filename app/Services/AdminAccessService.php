<?php

namespace App\Services;

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Server-side boundary for admin-panel identity and access mutations. */
class AdminAccessService
{
    public static function isSuperAdmin(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user instanceof User && $user->roles()
            ->where('guard_name', 'web')->where('name', 'super_admin')->exists();
    }

    public static function canEditUser(User $target): bool
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            return false;
        }

        if (self::isSuperAdmin($actor)) {
            return true;
        }

        if (self::isSuperAdmin($target) || ! $actor->fresh()?->can('manage_user')) {
            return false;
        }

        // A password/email reset must not become a route into a stronger account.
        return $target->fresh()->getAllPermissions()->pluck('id')
            ->diff($actor->fresh()->getAllPermissions()->pluck('id'))->isEmpty();
    }

    public static function isReservedRoleName(string $name): bool
    {
        return in_array($name, ['super_admin', 'G.O', TriumphantIdService::MAIN_PASTOR_ROLE, TriumphantIdService::IT_MANAGER_ROLE], true);
    }

    public static function canDeleteUser(User $target): bool
    {
        return self::isSuperAdmin() && (! self::isSuperAdmin($target) || self::hasOtherSuperAdmin($target));
    }

    public function saveUser(?User $target, array $attributes, array $submittedAccess): User
    {
        return DB::transaction(function () use ($target, $attributes, $submittedAccess): User {
            $this->lockSuperAdminRole();
            $actor = $this->actor();
            $isSuperAdmin = self::isSuperAdmin($actor);
            $creating = $target === null;
            $target = $target ? User::query()->lockForUpdate()->findOrFail($target->id) : new User;
            abort_unless($creating ? $isSuperAdmin : self::canEditUser($target), 403);
            $before = $creating ? [] : $this->userSnapshot($target);

            if (! $isSuperAdmin) {
                foreach (['roles', 'permissions'] as $relation) {
                    if (array_key_exists($relation, $submittedAccess)
                        && $this->ids($submittedAccess[$relation]) !== $this->ids($target->{$relation}()->pluck('id')->all())) {
                        throw ValidationException::withMessages([$relation => 'Only a Super Admin can change roles or permissions.']);
                    }
                }
            }

            $roles = $isSuperAdmin && array_key_exists('roles', $submittedAccess)
                ? $this->validatedRoles($submittedAccess['roles']) : null;
            $permissions = $isSuperAdmin && array_key_exists('permissions', $submittedAccess)
                ? $this->validatedPermissions($submittedAccess['permissions'], 'web') : null;
            if (! $creating && $roles !== null && self::isSuperAdmin($target)
                && ! $roles->contains('name', 'super_admin') && ! self::hasOtherSuperAdmin($target)) {
                throw ValidationException::withMessages(['roles' => 'Keep at least one Super Admin account.']);
            }

            // Never mass-assign access relations or unrelated attributes from a request.
            $target->fill(collect($attributes)->only(['name', 'email', 'password'])->all());
            if ($isSuperAdmin && array_key_exists('email_verified_at', $attributes)) {
                $target->email_verified_at = $attributes['email_verified_at'];
            }
            $target->save();
            if ($roles !== null) {
                $target->syncRoles($roles);
            }
            if ($permissions !== null) {
                $target->syncPermissions($permissions->merge($this->uncataloguedPermissions($target, 'web')));
            }
            $after = $this->userSnapshot($target->fresh());
            if ($creating || $before !== $after) {
                $this->audit($actor, 'user', $target->id, $creating ? 'created' : 'access.updated', $before, $after);
            }

            return $target->refresh();
        });
    }

    public function saveRole(?Role $target, array $attributes, mixed $permissionIds): Role
    {
        return DB::transaction(function () use ($target, $attributes, $permissionIds): Role {
            $this->lockSuperAdminRole();
            $actor = $this->actor();
            abort_unless(self::isSuperAdmin($actor), 403);
            $creating = $target === null;
            $target = $target ? Role::query()->lockForUpdate()->findOrFail($target->id) : new Role;
            $before = $creating ? [] : $this->roleSnapshot($target);
            $guard = $attributes['guard_name'] ?? $target->guard_name;
            $name = $attributes['name'] ?? $target->name;
            if (! in_array($guard, ['web', 'mobile'], true) || (! $creating && $guard !== $target->guard_name)) {
                throw ValidationException::withMessages(['guard_name' => 'An existing role cannot change its role type.']);
            }
            if ((! $creating && $name !== $target->name && (self::isReservedRoleName($name) || self::isReservedRoleName($target->name)))
                || ($name === 'super_admin' && $guard !== 'web')) {
                throw ValidationException::withMessages(['name' => 'Reserved role names cannot be changed.']);
            }
            $permissions = $this->validatedPermissions($permissionIds, $guard);
            $retained = $creating ? collect() : $this->uncataloguedPermissions($target, $guard);
            $target->fill(['name' => $name, 'guard_name' => $guard])->save();
            $target->syncPermissions($permissions->merge($retained));
            $after = $this->roleSnapshot($target->fresh());
            if ($creating || $before !== $after) {
                $this->audit($actor, 'role', $target->id, $creating ? 'created' : 'updated', $before, $after);
            }

            return $target->refresh();
        });
    }

    public function deleteUser(User $target): bool
    {
        return DB::transaction(function () use ($target): bool {
            $this->lockSuperAdminRole();
            $actor = $this->actor();
            $target = User::query()->lockForUpdate()->findOrFail($target->id);
            abort_unless(self::canDeleteUser($target), 403);
            $this->audit($actor, 'user', $target->id, 'deleted', $this->userSnapshot($target), []);

            return (bool) $target->delete();
        });
    }

    public function deleteRole(Role $target): bool
    {
        return DB::transaction(function () use ($target): bool {
            $this->lockSuperAdminRole();
            $actor = $this->actor();
            $target = Role::query()->lockForUpdate()->findOrFail($target->id);
            abort_unless(self::isSuperAdmin($actor) && ! self::isReservedRoleName($target->name), 403);
            $this->audit($actor, 'role', $target->id, 'deleted', $this->roleSnapshot($target), []);

            return (bool) $target->delete();
        });
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return $actor->fresh() ?? abort(403);
    }

    private function lockSuperAdminRole(): void
    {
        Role::query()->where('guard_name', 'web')->where('name', 'super_admin')->lockForUpdate()->first();
    }

    private static function hasOtherSuperAdmin(User $target): bool
    {
        return User::query()->whereKeyNot($target->id)->whereHas('roles', fn ($query) => $query
            ->where('guard_name', 'web')->where('name', 'super_admin'))->exists();
    }

    private function ids(mixed $values): array
    {
        if (! is_array($values) || collect($values)->contains(fn ($id) => ! is_int($id) && (! is_string($id) || ! ctype_digit($id)))) {
            throw ValidationException::withMessages(['permissions' => 'Choose valid role or permission IDs.']);
        }

        return collect($values)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    private function validatedRoles(mixed $values)
    {
        $ids = $this->ids($values);
        $roles = Role::query()->where('guard_name', 'web')->whereIn('id', $ids)->get();
        if ($roles->count() !== count($ids)) {
            throw ValidationException::withMessages(['roles' => 'Choose valid web admin roles.']);
        }

        return $roles;
    }

    private function validatedPermissions(mixed $values, string $guard)
    {
        $ids = $this->ids($values);
        $permissions = Permission::query()->where('guard_name', $guard)->whereIn('id', $ids)
            ->when($guard === 'web', fn ($query) => $query->whereIn('name', AdminPermissions::names()))->get();
        if ($permissions->count() !== count($ids)) {
            throw ValidationException::withMessages(['permissions' => 'Choose permissions from the current role type and catalog.']);
        }

        return $permissions;
    }

    private function uncataloguedPermissions(User|Role $target, string $guard)
    {
        return $guard === 'web' ? $target->permissions()->where('guard_name', 'web')
            ->whereNotIn('name', AdminPermissions::names())->get() : collect();
    }

    private function userSnapshot(User $target): array
    {
        return ['roles' => $target->roles()->orderBy('id')->get(['id', 'name', 'guard_name'])->toArray(),
            'permissions' => $target->permissions()->orderBy('id')->get(['id', 'name', 'guard_name'])->toArray()];
    }

    private function roleSnapshot(Role $target): array
    {
        return ['name' => $target->name, 'guard_name' => $target->guard_name,
            'permissions' => $target->permissions()->orderBy('id')->get(['id', 'name', 'guard_name'])->toArray()];
    }

    private function audit(User $actor, string $type, int $id, string $action, array $before, array $after): void
    {
        DB::table('admin_access_audits')->insert(['actor_id' => $actor->id, 'target_type' => $type,
            'target_id' => $id, 'action' => $action, 'before' => json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
