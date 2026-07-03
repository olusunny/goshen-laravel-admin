<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_users') && ! Schema::hasColumn('mobile_users', 'notification_preferences')) {
            Schema::table('mobile_users', function (Blueprint $table): void {
                $table->json('notification_preferences')->nullable()->after('state_county_province');
            });
        }

        if (Schema::hasTable('inbox_messages') && ! Schema::hasColumn('inbox_messages', 'notification_category')) {
            Schema::table('inbox_messages', function (Blueprint $table): void {
                $table->string('notification_category', 80)->default('general')->after('title')->index();
            });
        }

        if (Schema::hasTable('automatic_notifications') && ! Schema::hasColumn('automatic_notifications', 'notification_category')) {
            Schema::table('automatic_notifications', function (Blueprint $table): void {
                $table->string('notification_category', 80)->default('general')->after('event_key')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('automatic_notifications') && Schema::hasColumn('automatic_notifications', 'notification_category')) {
            Schema::table('automatic_notifications', function (Blueprint $table): void {
                $table->dropColumn('notification_category');
            });
        }

        if (Schema::hasTable('inbox_messages') && Schema::hasColumn('inbox_messages', 'notification_category')) {
            Schema::table('inbox_messages', function (Blueprint $table): void {
                $table->dropColumn('notification_category');
            });
        }

        if (Schema::hasTable('mobile_users') && Schema::hasColumn('mobile_users', 'notification_preferences')) {
            Schema::table('mobile_users', function (Blueprint $table): void {
                $table->dropColumn('notification_preferences');
            });
        }
    }
};
