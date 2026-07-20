<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('redeem_wallet_funding_vouchers_for_members', 'mobile');

        Role::query()
            ->where('guard_name', 'mobile')
            ->whereIn('name', [
                'admin',
                'super_admin',
                'event_manager',
                'goshen_manager',
                'retreat_manager',
                'wallet_manager',
                'goshen_wallet_manager',
                'triumphant_it_manager',
            ])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'mobile')
            ->where('name', 'redeem_wallet_funding_vouchers_for_members')
            ->delete();
    }
};
