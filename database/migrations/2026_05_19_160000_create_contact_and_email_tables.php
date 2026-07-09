<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_users') && ! Schema::hasColumn('mobile_users', 'phone')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->string('phone')->nullable()->after('email')->index('mobile_users_phone_idx');
            });
        }

        Schema::create('contact_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->boolean('is_active')->default(true)->index('contact_recipient_active_idx');
            $table->unsignedInteger('sort_order')->default(0)->index('contact_recipient_sort_idx');
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('status')->default('new')->index('contact_message_status_idx');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('smtp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Zoho SMTP');
            $table->string('host')->default('smtp.zoho.com');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address');
            $table->string('from_name')->default('MFM Triumphant Church');
            $table->boolean('is_active')->default(true)->index('smtp_setting_active_idx');
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_test_result')->nullable();
            $table->timestamps();
        });

        Schema::create('email_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->longText('body');
            $table->string('recipient_mode')->default('all');
            $table->json('selected_mobile_user_ids')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notifications');
        Schema::dropIfExists('smtp_settings');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('contact_recipients');

        if (Schema::hasTable('mobile_users') && Schema::hasColumn('mobile_users', 'phone')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
