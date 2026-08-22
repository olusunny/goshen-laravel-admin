<?php

use App\Models\Addon;
use App\Services\TriumphantIdService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const MOBILE_PERMISSIONS = [
        'manage_goshen_registration',
        'manage_goshen_vouchers',
        'charge_goshen_member_wallet',
        'manage_goshen_quiz',
        'manage_fundraising',
        'manage_goshen_wallet_withdrawals',
        'manage_dynamic_forms',
        'manage_church_events',
        'manage_verse_of_day',
        'manage_mobile_users',
        'manage_counseling',
        'send_admin_messages',
        'redeem_wallet_funding_vouchers_for_members',
        'scan_goshen_tickets',
        'manage_goshen_scanners',
    ];

    public function up(): void
    {
        foreach (self::MOBILE_PERMISSIONS as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'mobile',
            ]);
        }

        $itManager = Role::findOrCreate(TriumphantIdService::IT_MANAGER_ROLE, 'mobile');

        // The reserved role is the church's mobile Control Hub administrator.
        // Its complete app capability set remains visible and editable on the role form.
        $addonPermissions = Addon::query()
            ->where('status', Addon::STATUS_ACTIVE)
            ->get(['manifest'])
            ->flatMap(function (Addon $addon): array {
                $capabilities = $addon->manifest['capabilities'] ?? [];

                return is_array($capabilities)
                    ? collect($capabilities)
                        ->filter(fn (mixed $capability): bool => is_array($capability))
                        ->flatMap(function (array $capability): array {
                            $permissions = $capability['permissions'] ?? [];

                            return is_array($permissions) ? $permissions : [];
                        })
                        ->filter(fn (mixed $permission): bool => is_string($permission))
                        ->values()
                        ->all()
                    : [];
            });

        $permissions = Permission::query()
            ->where('guard_name', 'mobile')
            ->whereIn('name', collect(self::MOBILE_PERMISSIONS)->merge($addonPermissions)->unique())
            ->get();

        // Preserve any bespoke permission choices already saved for this role.
        $itManager->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Permission grants are operational configuration. Do not remove a
        // pre-existing permission or an administrator's later role selection.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
