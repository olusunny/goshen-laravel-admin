<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')->insertOrIgnore([
            'group' => 'performance',
            'key' => 'redis_cache_enabled',
            'value' => '0',
            'is_secret' => false,
            'description' => 'Use Redis for application cache with database failover.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'redis_cache_enabled')->delete();
    }
};
