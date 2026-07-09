<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('automatic_notification_deliveries');
        Schema::dropIfExists('automatic_notifications');

        Schema::create('automatic_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('title_template');
            $table->longText('body_template');
            $table->string('image_path')->nullable();
            $table->boolean('send_email')->default(false);
            $table->boolean('send_inbox')->default(true);
            $table->boolean('send_push')->default(true);
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('automatic_notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('automatic_notification_id');
            $table->unsignedBigInteger('mobile_user_id');
            $table->string('event_key')->index();
            $table->string('status')->default('pending')->index();
            $table->json('context')->nullable();
            $table->timestamp('scheduled_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->foreign('automatic_notification_id', 'auto_notif_delivery_notif_fk')->references('id')->on('automatic_notifications')->cascadeOnDelete();
            $table->foreign('mobile_user_id', 'auto_notif_delivery_user_fk')->references('id')->on('mobile_users')->cascadeOnDelete();
            $table->unique(['event_key', 'mobile_user_id'], 'auto_notification_once_per_user');
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('automatic_notification_deliveries');
        Schema::dropIfExists('automatic_notifications');
    }

    private function seedDefaults(): void
    {
        $notifications = [
            [
                'event_key' => 'welcome_verified_user',
                'name' => 'Welcome after verified registration',
                'description' => 'Sent 5 minutes after a user verifies registration. Email is intentionally disabled.',
                'title_template' => 'Welcome to MFM Triumphant Church, {user_name}',
                'body_template' => "Hello {user_name},\n\nWelcome to the MFM Triumphant Church family. We are truly glad to have you here.\n\nYou can now explore messages, events, giving, Bible tools, church groups, and the Interactive Prayer Wall. May this app help you stay connected, encouraged, and covered in prayer.\n\nGod bless you.",
                'send_email' => false,
                'send_inbox' => true,
                'send_push' => true,
                'delay_minutes' => 5,
                'is_active' => true,
            ],
            [
                'event_key' => 'accommodation_booking_created',
                'name' => 'Accommodation booking created',
                'description' => 'Template reference for accommodation booking-created notifications.',
                'title_template' => 'Accommodation booking received: {booking_reference}',
                'body_template' => 'Hello {user_name}, your accommodation booking has been received. Please complete payment from the app to confirm your booking.',
                'send_email' => true,
                'send_inbox' => true,
                'send_push' => true,
                'delay_minutes' => 0,
                'is_active' => true,
            ],
            [
                'event_key' => 'accommodation_payment_successful',
                'name' => 'Accommodation payment successful',
                'description' => 'Template reference for accommodation payment receipts.',
                'title_template' => 'Accommodation payment receipt: {booking_reference}',
                'body_template' => 'Hello {user_name}, thank you. Your accommodation payment was successful.',
                'send_email' => true,
                'send_inbox' => true,
                'send_push' => true,
                'delay_minutes' => 0,
                'is_active' => true,
            ],
            [
                'event_key' => 'accommodation_checkout_reminder',
                'name' => 'Accommodation checkout reminder',
                'description' => 'Template reference for checkout reminder notifications.',
                'title_template' => 'Friendly checkout reminder',
                'body_template' => 'Hello {user_name}, we hope you have enjoyed your stay. This is a friendly reminder to prepare for checkout.',
                'send_email' => true,
                'send_inbox' => true,
                'send_push' => true,
                'delay_minutes' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($notifications as $notification) {
            DB::table('automatic_notifications')->updateOrInsert(
                ['event_key' => $notification['event_key']],
                $notification + ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }
};
