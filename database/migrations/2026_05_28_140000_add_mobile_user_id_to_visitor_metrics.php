<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visitor_metrics') || Schema::hasColumn('visitor_metrics', 'mobile_user_id')) {
            return;
        }

        Schema::table('visitor_metrics', function (Blueprint $table) {
            $table->foreignId('mobile_user_id')
                ->nullable()
                ->after('id')
                ->constrained('mobile_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visitor_metrics') || ! Schema::hasColumn('visitor_metrics', 'mobile_user_id')) {
            return;
        }

        Schema::table('visitor_metrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mobile_user_id');
        });
    }
};
