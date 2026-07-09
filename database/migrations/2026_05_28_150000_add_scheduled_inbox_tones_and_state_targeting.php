<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_users') && ! Schema::hasColumn('mobile_users', 'state_county_province')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->string('state_county_province', 120)->nullable()->after('country_of_residence')->index();
            });
        }

        if (Schema::hasTable('inbox_messages')) {
            Schema::table('inbox_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('inbox_messages', 'selected_states_counties_provinces')) {
                    $table->json('selected_states_counties_provinces')->nullable()->after('selected_country_of_residences');
                }

                if (! Schema::hasColumn('inbox_messages', 'schedule_enabled')) {
                    $table->boolean('schedule_enabled')->default(false)->after('send_push')->index();
                }

                if (! Schema::hasColumn('inbox_messages', 'schedule_type')) {
                    $table->string('schedule_type', 40)->default('manual')->after('schedule_enabled')->index();
                }

                if (! Schema::hasColumn('inbox_messages', 'scheduled_for')) {
                    $table->timestamp('scheduled_for')->nullable()->after('schedule_type')->index();
                }

                if (! Schema::hasColumn('inbox_messages', 'recurring_time')) {
                    $table->string('recurring_time', 5)->nullable()->after('scheduled_for');
                }

                if (! Schema::hasColumn('inbox_messages', 'recurring_timezone')) {
                    $table->string('recurring_timezone', 80)->default('Africa/Lagos')->after('recurring_time');
                }

                if (! Schema::hasColumn('inbox_messages', 'next_dispatch_at')) {
                    $table->timestamp('next_dispatch_at')->nullable()->after('recurring_timezone')->index();
                }

                if (! Schema::hasColumn('inbox_messages', 'last_dispatched_at')) {
                    $table->timestamp('last_dispatched_at')->nullable()->after('next_dispatch_at');
                }

                if (! Schema::hasColumn('inbox_messages', 'scheduled_parent_id')) {
                    $table->foreignId('scheduled_parent_id')->nullable()->after('last_dispatched_at')->constrained('inbox_messages')->nullOnDelete();
                }

                if (! Schema::hasColumn('inbox_messages', 'notification_tone_enabled')) {
                    $table->boolean('notification_tone_enabled')->default(false)->after('thumbnail');
                }

                if (! Schema::hasColumn('inbox_messages', 'notification_tone_path')) {
                    $table->string('notification_tone_path')->nullable()->after('notification_tone_enabled');
                }

                if (! Schema::hasColumn('inbox_messages', 'notification_tone_label')) {
                    $table->string('notification_tone_label')->nullable()->after('notification_tone_path');
                }
            });
        }

        if (Schema::hasTable('email_notifications') && ! Schema::hasColumn('email_notifications', 'selected_states_counties_provinces')) {
            Schema::table('email_notifications', function (Blueprint $table) {
                $table->json('selected_states_counties_provinces')->nullable()->after('selected_country_of_residences');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_notifications') && Schema::hasColumn('email_notifications', 'selected_states_counties_provinces')) {
            Schema::table('email_notifications', function (Blueprint $table) {
                $table->dropColumn('selected_states_counties_provinces');
            });
        }

        if (Schema::hasTable('inbox_messages')) {
            Schema::table('inbox_messages', function (Blueprint $table) {
                foreach ([
                    'selected_states_counties_provinces',
                    'schedule_enabled',
                    'schedule_type',
                    'scheduled_for',
                    'recurring_time',
                    'recurring_timezone',
                    'next_dispatch_at',
                    'last_dispatched_at',
                    'notification_tone_enabled',
                    'notification_tone_path',
                    'notification_tone_label',
                ] as $column) {
                    if (Schema::hasColumn('inbox_messages', $column)) {
                        $table->dropColumn($column);
                    }
                }

                if (Schema::hasColumn('inbox_messages', 'scheduled_parent_id')) {
                    $table->dropConstrainedForeignId('scheduled_parent_id');
                }
            });
        }

        if (Schema::hasTable('mobile_users') && Schema::hasColumn('mobile_users', 'state_county_province')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->dropColumn('state_county_province');
            });
        }
    }
};
