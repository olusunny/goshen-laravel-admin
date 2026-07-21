<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('charge_goshen_member_wallet', 'mobile');
    }

    public function down(): void
    {
        Permission::query()
            ->where('guard_name', 'mobile')
            ->where('name', 'charge_goshen_member_wallet')
            ->delete();
    }
};
