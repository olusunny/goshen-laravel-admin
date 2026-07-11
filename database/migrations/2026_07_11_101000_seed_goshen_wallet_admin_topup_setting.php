<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => 'goshen_wallet_admin_topup_enabled'],
            [
                'group' => 'features',
                'value' => '1',
                'is_secret' => false,
                'description' => 'Allow authorized admins to add funds directly to a member wallet from the admin panel.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('app_settings')
            ->where('key', 'goshen_wallet_admin_topup_enabled')
            ->delete();
    }
};
