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
            if (! Schema::hasColumn('mobile_users', 'membership_status_changed_at')) {
                $table->timestamp('membership_status_changed_at')->nullable()->after('member_type')->index();
            }

            if (! Schema::hasColumn('mobile_users', 'birthday_month')) {
                $table->unsignedTinyInteger('birthday_month')->nullable()->after('membership_status_changed_at');
            }

            if (! Schema::hasColumn('mobile_users', 'birthday_day')) {
                $table->unsignedTinyInteger('birthday_day')->nullable()->after('birthday_month');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mobile_users')) {
            return;
        }

        Schema::table('mobile_users', function (Blueprint $table): void {
            foreach (['birthday_day', 'birthday_month', 'membership_status_changed_at'] as $column) {
                if (Schema::hasColumn('mobile_users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
