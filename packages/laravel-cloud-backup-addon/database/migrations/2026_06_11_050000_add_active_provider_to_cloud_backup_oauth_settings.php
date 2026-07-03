<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('cloud_backup_oauth_settings')) {
            return;
        }

        if (! Schema::hasColumn('cloud_backup_oauth_settings', 'is_active')) {
            Schema::table('cloud_backup_oauth_settings', function (Blueprint $table): void {
                $table->boolean('is_active')->default(false)->after('redirect_uri')->index();
            });
        }

        if (! DB::table('cloud_backup_oauth_settings')->where('is_active', true)->exists()) {
            DB::table('cloud_backup_oauth_settings')->updateOrInsert(
                ['provider' => 'google'],
                [
                    'tenant' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cloud_backup_oauth_settings') || ! Schema::hasColumn('cloud_backup_oauth_settings', 'is_active')) {
            return;
        }

        Schema::table('cloud_backup_oauth_settings', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
