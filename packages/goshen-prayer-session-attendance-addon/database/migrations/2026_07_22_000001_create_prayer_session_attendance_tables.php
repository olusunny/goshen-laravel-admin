<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prayer_attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('event_id')->constrained('ei_events')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('scheduled_starts_at')->nullable();
            $table->timestamp('scheduled_ends_at')->nullable();
            $table->string('status', 20)->default('scheduled')->index();
            $table->timestamp('activated_at')->nullable()->index();
            $table->unsignedBigInteger('activated_by_mobile_user_id')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->unsignedBigInteger('closed_by_mobile_user_id')->nullable()->index();
            $table->string('qr_generation_id', 64)->nullable();
            $table->string('qr_token_hash', 128)->nullable();
            $table->timestamp('qr_activated_at')->nullable();
            $table->timestamp('activation_notification_dispatched_at')->nullable();
            $table->timestamp('reminder_dispatched_at')->nullable();
            $table->unsignedBigInteger('reminder_sent_by_mobile_user_id')->nullable()->index();
            $table->unsignedInteger('reopened_count')->default(0);
            $table->string('last_reopen_reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status']);
        });

        Schema::create('prayer_attendance_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prayer_session_id')->constrained('prayer_attendance_sessions')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('ei_tickets')->restrictOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained('ei_attendees')->nullOnDelete();
            $table->string('method', 40)->index();
            $table->unsignedBigInteger('recorded_by_mobile_user_id')->nullable()->index();
            $table->timestamp('confirmed_at')->index();
            $table->string('idempotency_key', 120)->nullable();
            $table->string('source', 40)->nullable();
            $table->json('source_metadata')->nullable();
            $table->timestamp('voided_at')->nullable()->index();
            $table->unsignedBigInteger('voided_by_mobile_user_id')->nullable()->index();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['prayer_session_id', 'ticket_id'], 'prayer_attendance_session_ticket_unique');
            $table->unique(['prayer_session_id', 'idempotency_key'], 'prayer_attendance_session_key_unique');
        });

        Schema::create('prayer_attendance_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prayer_session_id')->constrained('prayer_attendance_sessions')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('ei_tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('mobile_user_id')->nullable()->index();
            $table->string('kind', 30)->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('inbox_message_id')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamps();
            $table->unique(['prayer_session_id', 'ticket_id', 'kind'], 'prayer_attendance_delivery_unique');
        });

        Schema::create('prayer_attendance_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prayer_session_id')->constrained('prayer_attendance_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('actor_mobile_user_id')->nullable()->index();
            $table->string('action', 80)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_attendance_audits');
        Schema::dropIfExists('prayer_attendance_notification_deliveries');
        Schema::dropIfExists('prayer_attendance_confirmations');
        Schema::dropIfExists('prayer_attendance_sessions');
    }
};
