<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodation_bookings', function (Blueprint $table) {
            foreach ([
                'booking_created_notified_at',
                'payment_pending_notified_at',
                'payment_success_notified_at',
                'payment_failed_notified_at',
                'booking_confirmed_notified_at',
                'booking_cancelled_notified_at',
                'checkout_reminder_sent_at',
            ] as $column) {
                if (! Schema::hasColumn('accommodation_bookings', $column)) {
                    $table->timestamp($column)->nullable()->after('expires_at');
                }
            }
        });

        $settings = [
            [
                'group' => 'accommodation',
                'key' => 'accommodation_booking_support_name',
                'value' => 'Accommodation Support',
                'description' => 'Support contact name shown on accommodation booking notifications and receipts.',
            ],
            [
                'group' => 'accommodation',
                'key' => 'accommodation_booking_support_email',
                'value' => '',
                'description' => 'Support email shown on accommodation booking notifications and receipts.',
            ],
            [
                'group' => 'accommodation',
                'key' => 'accommodation_booking_support_phone',
                'value' => '',
                'description' => 'Support phone number shown on accommodation booking notifications and receipts.',
            ],
            [
                'group' => 'accommodation',
                'key' => 'accommodation_booking_support_whatsapp',
                'value' => '',
                'description' => 'Support WhatsApp contact shown on accommodation booking notifications and receipts.',
            ],
            [
                'group' => 'accommodation',
                'key' => 'accommodation_booking_support_instructions',
                'value' => 'Please contact us if you need help before check-in or during checkout.',
                'description' => 'Friendly support instructions shown on accommodation booking messages.',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting + ['is_secret' => false, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        Schema::table('accommodation_bookings', function (Blueprint $table) {
            foreach ([
                'booking_created_notified_at',
                'payment_pending_notified_at',
                'payment_success_notified_at',
                'payment_failed_notified_at',
                'booking_confirmed_notified_at',
                'booking_cancelled_notified_at',
                'checkout_reminder_sent_at',
            ] as $column) {
                if (Schema::hasColumn('accommodation_bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::table('app_settings')
            ->whereIn('key', [
                'accommodation_booking_support_name',
                'accommodation_booking_support_email',
                'accommodation_booking_support_phone',
                'accommodation_booking_support_whatsapp',
                'accommodation_booking_support_instructions',
            ])
            ->delete();
    }
};
