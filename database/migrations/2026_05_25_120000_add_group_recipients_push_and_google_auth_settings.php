<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_notifications') && ! Schema::hasColumn('email_notifications', 'selected_church_group_ids')) {
            Schema::table('email_notifications', function (Blueprint $table) {
                $table->json('selected_church_group_ids')->nullable()->after('selected_mobile_user_ids');
            });
        }

        if (Schema::hasTable('inbox_messages')) {
            Schema::table('inbox_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('inbox_messages', 'recipient_mode')) {
                    $table->string('recipient_mode')->default('all')->after('send_push');
                }
                if (! Schema::hasColumn('inbox_messages', 'selected_mobile_user_ids')) {
                    $table->json('selected_mobile_user_ids')->nullable()->after('recipient_mode');
                }
                if (! Schema::hasColumn('inbox_messages', 'selected_church_group_ids')) {
                    $table->json('selected_church_group_ids')->nullable()->after('selected_mobile_user_ids');
                }
                if (! Schema::hasColumn('inbox_messages', 'push_sent_count')) {
                    $table->unsignedInteger('push_sent_count')->default(0)->after('selected_church_group_ids');
                }
                if (! Schema::hasColumn('inbox_messages', 'push_failed_count')) {
                    $table->unsignedInteger('push_failed_count')->default(0)->after('push_sent_count');
                }
                if (! Schema::hasColumn('inbox_messages', 'push_sent_at')) {
                    $table->timestamp('push_sent_at')->nullable()->after('push_failed_count');
                }
                if (! Schema::hasColumn('inbox_messages', 'push_last_error')) {
                    $table->text('push_last_error')->nullable()->after('push_sent_at');
                }
            });
        }

        if (Schema::hasTable('mobile_users') && ! Schema::hasColumn('mobile_users', 'google_id')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->string('google_id')->nullable()->after('email')->index();
            });
        }

        $this->seedGoogleAuthSettings();
    }

    public function down(): void
    {
        if (Schema::hasTable('email_notifications') && Schema::hasColumn('email_notifications', 'selected_church_group_ids')) {
            Schema::table('email_notifications', function (Blueprint $table) {
                $table->dropColumn('selected_church_group_ids');
            });
        }

        if (Schema::hasTable('inbox_messages')) {
            Schema::table('inbox_messages', function (Blueprint $table) {
                foreach ([
                    'recipient_mode',
                    'selected_mobile_user_ids',
                    'selected_church_group_ids',
                    'push_sent_count',
                    'push_failed_count',
                    'push_sent_at',
                    'push_last_error',
                ] as $column) {
                    if (Schema::hasColumn('inbox_messages', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('mobile_users') && Schema::hasColumn('mobile_users', 'google_id')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->dropColumn('google_id');
            });
        }
    }

    private function seedGoogleAuthSettings(): void
    {
        $settings = [
            ['group' => 'auth', 'key' => 'google_login_enabled', 'value' => '0', 'is_secret' => false, 'description' => 'Enable native Google sign-in and registration in the mobile app.'],
            ['group' => 'auth', 'key' => 'google_web_client_id', 'value' => '', 'is_secret' => false, 'description' => 'Google OAuth Web client ID used by the mobile app to request an ID token.'],
            ['group' => 'auth', 'key' => 'google_android_client_id', 'value' => '', 'is_secret' => false, 'description' => 'Google OAuth Android client ID for the app package and signing certificate.'],
            ['group' => 'auth', 'key' => 'google_ios_client_id', 'value' => '', 'is_secret' => false, 'description' => 'Optional Google OAuth iOS client ID.'],
            ['group' => 'auth', 'key' => 'google_client_secret', 'value' => '', 'is_secret' => true, 'description' => 'Optional Google OAuth client secret. Keep this private.'],
        ];

        foreach ($settings as $setting) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }
};
