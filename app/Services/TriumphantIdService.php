<?php

namespace App\Services;

use App\Models\MobileUser;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class TriumphantIdService
{
    public const MAIN_PASTOR_ROLE = 'Triumphant main pastor';

    public const IT_MANAGER_ROLE = 'Triumphant IT Manager';

    public const MAIN_PASTOR_SEQUENCE = 1;

    public const IT_MANAGER_SEQUENCE = 2;

    public function ensureRoles(): void
    {
        Role::query()->firstOrCreate(['name' => self::MAIN_PASTOR_ROLE, 'guard_name' => 'mobile']);
        Role::query()->firstOrCreate(['name' => self::IT_MANAGER_ROLE, 'guard_name' => 'mobile']);

        Role::query()->firstOrCreate(['name' => self::MAIN_PASTOR_ROLE, 'guard_name' => 'web']);
        $webItManager = Role::query()->firstOrCreate(['name' => self::IT_MANAGER_ROLE, 'guard_name' => 'web']);
        $permissions = Permission::query()->where('guard_name', 'web')->pluck('name')->all();

        if ($permissions !== []) {
            $webItManager->syncPermissions($permissions);
        }
    }

    public function formatted(?int $sequence): ?string
    {
        if (! $sequence || $sequence < 1) {
            return null;
        }

        return 'T' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    public function assignFor(MobileUser $user): MobileUser
    {
        $this->ensureRoles();

        return DB::transaction(function () use ($user): MobileUser {
            /** @var MobileUser $locked */
            $locked = MobileUser::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->is_deleted || $this->isVisitor($locked)) {
                return $this->release($locked);
            }

            $reservedSequence = $this->reservedSequenceFor($locked);

            if ($reservedSequence !== null) {
                $this->assertOnlyReservedRoleHolder($locked, $reservedSequence);

                return $this->setSequence($locked, $reservedSequence);
            }

            if (
                $locked->triumphant_id_sequence
                && (int) $locked->triumphant_id_sequence >= 3
                && $locked->triumphant_id === $this->formatted((int) $locked->triumphant_id_sequence)
            ) {
                return $locked;
            }

            return $this->setSequence($locked, $this->nextAvailableGeneralSequence());
        });
    }

    public function release(MobileUser $user): MobileUser
    {
        $user->forceFill([
            'triumphant_id_sequence' => null,
            'triumphant_id' => null,
        ])->saveQuietly();

        return $user;
    }

    private function isVisitor(MobileUser $user): bool
    {
        return str($user->member_type)->trim()->lower()->toString() === 'visitor';
    }

    /**
     * @param  array<int|string>  $roleIds
     */
    public function assertReservedMobileRolesAvailable(array $roleIds, ?MobileUser $record = null): void
    {
        $this->ensureRoles();

        $reservedRoles = Role::query()
            ->where('guard_name', 'mobile')
            ->whereIn('name', [self::MAIN_PASTOR_ROLE, self::IT_MANAGER_ROLE])
            ->whereIn('id', $roleIds)
            ->pluck('name')
            ->all();

        foreach ($reservedRoles as $roleName) {
            $existing = MobileUser::query()
                ->where('is_deleted', false)
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->whereHas('roles', fn ($query) => $query
                    ->where('roles.guard_name', 'mobile')
                    ->where('roles.name', $roleName))
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'roles' => "{$roleName} is already assigned to {$existing->name}. Only one user can hold this role.",
                ]);
            }
        }
    }

    public function assertReservedMobileRoleArgumentsAvailable(array $roles, ?MobileUser $record = null): void
    {
        $this->assertReservedMobileRolesAvailable(
            $this->resolveRoleIdsFromArguments($roles, 'mobile'),
            $record,
        );
    }

    /**
     * @param  array<int|string>  $roleIds
     */
    public function assertReservedWebRolesAvailable(array $roleIds, ?User $record = null): void
    {
        $this->ensureRoles();

        $reservedRoles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', [self::MAIN_PASTOR_ROLE, self::IT_MANAGER_ROLE])
            ->whereIn('id', $roleIds)
            ->pluck('name')
            ->all();

        foreach ($reservedRoles as $roleName) {
            $existing = User::query()
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->whereHas('roles', fn ($query) => $query
                    ->where('roles.guard_name', 'web')
                    ->where('roles.name', $roleName))
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'roles' => "{$roleName} is already assigned to {$existing->name}. Only one admin user can hold this role.",
                ]);
            }
        }
    }

    public function assertReservedWebRoleArgumentsAvailable(array $roles, ?User $record = null): void
    {
        $this->assertReservedWebRolesAvailable(
            $this->resolveRoleIdsFromArguments($roles, 'web'),
            $record,
        );
    }

    private function reservedSequenceFor(MobileUser $user): ?int
    {
        $roles = $user->roles()
            ->where('roles.guard_name', 'mobile')
            ->pluck('roles.name')
            ->all();

        if (in_array(self::MAIN_PASTOR_ROLE, $roles, true)) {
            return self::MAIN_PASTOR_SEQUENCE;
        }

        if (in_array(self::IT_MANAGER_ROLE, $roles, true)) {
            return self::IT_MANAGER_SEQUENCE;
        }

        return null;
    }

    private function resolveRoleIdsFromArguments(array $roles, string $guardName): array
    {
        $this->ensureRoles();

        return collect($this->flattenRoleArguments($roles))
            ->map(function (mixed $role) use ($guardName): ?int {
                if ($role instanceof Role) {
                    return $role->guard_name === $guardName ? (int) $role->id : null;
                }

                if ($role instanceof BackedEnum) {
                    $role = $role->value;
                }

                if (is_int($role) || (is_string($role) && ctype_digit($role))) {
                    return Role::query()
                        ->where('guard_name', $guardName)
                        ->whereKey((int) $role)
                        ->value('id');
                }

                if (is_string($role) && trim($role) !== '') {
                    return Role::query()
                        ->where('guard_name', $guardName)
                        ->where('name', trim($role))
                        ->value('id');
                }

                return null;
            })
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function flattenRoleArguments(mixed $roles): array
    {
        $items = $roles instanceof Collection ? $roles->all() : (is_array($roles) ? $roles : [$roles]);
        $flat = [];

        foreach ($items as $item) {
            if ($item instanceof Collection || is_array($item)) {
                array_push($flat, ...$this->flattenRoleArguments($item));

                continue;
            }

            $flat[] = $item;
        }

        return $flat;
    }

    private function assertOnlyReservedRoleHolder(MobileUser $user, int $sequence): void
    {
        $roleName = $sequence === self::MAIN_PASTOR_SEQUENCE
            ? self::MAIN_PASTOR_ROLE
            : self::IT_MANAGER_ROLE;

        $existing = MobileUser::query()
            ->where('is_deleted', false)
            ->whereKeyNot($user->getKey())
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.guard_name', 'mobile')
                ->where('roles.name', $roleName))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'roles' => "{$roleName} is already assigned to {$existing->name}. Only one user can hold this role.",
            ]);
        }
    }

    private function nextAvailableGeneralSequence(): int
    {
        $used = MobileUser::query()
            ->where('is_deleted', false)
            ->whereNotNull('triumphant_id_sequence')
            ->lockForUpdate()
            ->pluck('triumphant_id_sequence')
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        $used = array_flip($used);
        $sequence = 3;

        while (isset($used[$sequence])) {
            $sequence++;
        }

        return $sequence;
    }

    private function setSequence(MobileUser $user, int $sequence): MobileUser
    {
        $user->forceFill([
            'triumphant_id_sequence' => $sequence,
            'triumphant_id' => $this->formatted($sequence),
        ])->saveQuietly();

        return $user->refresh();
    }
}
