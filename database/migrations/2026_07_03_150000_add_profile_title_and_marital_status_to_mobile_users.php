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

        Schema::table('mobile_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('mobile_users', 'title')) {
                $table->string('title', 20)->nullable()->after('name')->index();
            }

            if (! Schema::hasColumn('mobile_users', 'marital_status')) {
                $table->string('marital_status', 40)->nullable()->after('gender')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mobile_users')) {
            return;
        }

        Schema::table('mobile_users', function (Blueprint $table): void {
            foreach (['marital_status', 'title'] as $column) {
                if (Schema::hasColumn('mobile_users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
