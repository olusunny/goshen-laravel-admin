<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_users')) {
            return;
        }

        Schema::table('mobile_users', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('api_token_hash')->index();
            }

            if (! Schema::hasColumn('mobile_users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('last_login_at')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mobile_users')) {
            return;
        }

        Schema::table('mobile_users', function (Blueprint $table) {
            foreach (['last_seen_at', 'last_login_at'] as $column) {
                if (Schema::hasColumn('mobile_users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
