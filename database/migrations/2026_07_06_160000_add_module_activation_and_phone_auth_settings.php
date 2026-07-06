<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_users')) {
            Schema::table('mobile_users', function (Blueprint $table): void {
                if (! Schema::hasColumn('mobile_users', 'firebase_uid')) {
                    $table->string('firebase_uid')->nullable()->unique();
                }

                if (! Schema::hasColumn('mobile_users', 'phone_normalized')) {
                    $table->string('phone_normalized', 32)->nullable()->unique();
                }

                if (! Schema::hasColumn('mobile_users', 'phone_verified_at')) {
                    $table->timestamp('phone_verified_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('app_settings')) {
            return;
        }

        $now = now();
        foreach ($this->settings() as [$key, $value, $description]) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => 'features',
                    'value' => $value,
                    'is_secret' => false,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mobile_users')) {
            Schema::table('mobile_users', function (Blueprint $table): void {
                if (Schema::hasColumn('mobile_users', 'phone_verified_at')) {
                    $table->dropColumn('phone_verified_at');
                }

                if (Schema::hasColumn('mobile_users', 'phone_normalized')) {
                    $table->dropUnique(['phone_normalized']);
                    $table->dropColumn('phone_normalized');
                }

                if (Schema::hasColumn('mobile_users', 'firebase_uid')) {
                    $table->dropUnique(['firebase_uid']);
                    $table->dropColumn('firebase_uid');
                }
            });
        }

        if (! Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')
            ->whereIn('key', array_column($this->settings(), 0))
            ->delete();
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function settings(): array
    {
        return [
            ['fundraising_enabled', '1', 'Show Project support/Fundraising features in the mobile and web apps.'],
            ['prayer_points_enabled', '1', 'Show Prayer Points content in the mobile and web apps.'],
            ['interactive_prayer_wall_enabled', '1', 'Show the Interactive Prayer Wall module.'],
            ['hymns_enabled', '1', 'Show Hymns in the mobile app.'],
            ['devotionals_enabled', '1', 'Show Devotional content in the mobile app.'],
            ['verse_of_day_enabled', '1', 'Show Verse of the Day in the mobile app.'],
            ['transportation_arrangements_enabled', '1', 'Show transportation arrangement information.'],
            ['church_groups_enabled', '1', 'Show Church Groups and group requests.'],
            ['dynamic_forms_enabled', '1', 'Show On-demand Forms in the mobile and web apps.'],
            ['goshen_quiz_enabled', '1', 'Show Goshen Quiz in the mobile app.'],
            ['goshen_wallet_withdrawals_enabled', '1', 'Allow wallet withdrawal requests.'],
            ['goshen_wallet_auto_topup_enabled', '1', 'Allow recurring wallet auto top-up plans.'],
            ['branches_enabled', '1', 'Show Branches module in the mobile app.'],
            ['mobile_phone_otp_login_enabled', '1', 'Allow Firebase phone OTP sign-in in the mobile app.'],
        ];
    }
};
