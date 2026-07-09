<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_referral_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_user_id')->unique()->constrained('mobile_users')->cascadeOnDelete();
            $table->string('code', 32)->unique();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('goshen_referral_point_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->foreignId('referee_mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->foreignId('referral_code_id')->constrained('goshen_referral_codes')->restrictOnDelete();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained('ei_bookings')->cascadeOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained('ei_attendees')->nullOnDelete();
            $table->string('status', 40)->default('Pending Validation')->index();
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('converted_points')->default(0);
            $table->foreignId('wallet_ledger_entry_id')->nullable()->constrained('goshen_wallet_ledger_entries')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['referrer_mobile_user_id', 'status'], 'goshen_ref_points_referrer_status_idx');
            $table->index(['referee_mobile_user_id', 'event_id'], 'goshen_ref_points_referee_event_idx');
            $table->unique(
                ['referrer_mobile_user_id', 'referee_mobile_user_id', 'event_id'],
                'goshen_referral_referrer_referee_event_unique',
            );
        });

        $this->seedDefaultSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_referral_point_entries');
        Schema::dropIfExists('goshen_referral_codes');

        DB::table('app_settings')
            ->whereIn('key', [
                'goshen_referrals_enabled',
                'goshen_referral_points_per_paid_registration',
                'goshen_referral_wallet_amount_per_point',
                'goshen_referral_min_convertible_points',
            ])
            ->delete();
    }

    private function seedDefaultSettings(): void
    {
        $settings = [
            [
                'group' => 'goshen_referrals',
                'key' => 'goshen_referrals_enabled',
                'value' => '1',
                'is_secret' => false,
                'description' => 'Enable Goshen Retreat referral code capture, point validation, and wallet conversion.',
            ],
            [
                'group' => 'goshen_referrals',
                'key' => 'goshen_referral_points_per_paid_registration',
                'value' => '1',
                'is_secret' => false,
                'description' => 'Referral points awarded after a referred Goshen Retreat registration is paid.',
            ],
            [
                'group' => 'goshen_referrals',
                'key' => 'goshen_referral_wallet_amount_per_point',
                'value' => '0',
                'is_secret' => false,
                'description' => 'Wallet fund amount credited per validated referral point. Set this before enabling conversions.',
            ],
            [
                'group' => 'goshen_referrals',
                'key' => 'goshen_referral_min_convertible_points',
                'value' => '1',
                'is_secret' => false,
                'description' => 'Minimum validated referral points a member must have before converting to wallet fund.',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }
};
