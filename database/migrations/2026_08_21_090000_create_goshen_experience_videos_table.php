<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_experience_videos', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained('ei_events')->restrictOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('ei_bookings')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('ei_tickets')->nullOnDelete();
            $table->foreignId('goshen_experience_survey_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('client_upload_id');
            $table->string('caption', 500)->nullable();
            $table->string('display_name', 120)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('consent_version', 120);
            $table->timestamp('consented_at');
            $table->string('local_disk', 80);
            $table->string('local_path')->nullable();
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size_bytes');
            $table->decimal('duration_seconds', 8, 3);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->string('active_submission_key', 16)->nullable()->default('active');
            $table->unsignedBigInteger('youtube_connection_id')->nullable()->index();
            $table->string('youtube_video_id', 191)->nullable()->index();
            $table->string('youtube_url')->nullable();
            $table->string('youtube_thumbnail_url')->nullable();
            $table->string('youtube_privacy_status', 32)->nullable();
            $table->string('youtube_processing_status', 64)->nullable();
            $table->text('resumable_session_url')->nullable();
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->unsignedInteger('upload_attempts')->default(0);
            $table->timestamp('youtube_uploaded_at')->nullable();
            $table->timestamp('youtube_processed_at')->nullable();
            $table->string('upload_status', 40)->default('received')->index();
            $table->string('moderation_status', 32)->default('pending')->index();
            $table->string('last_error_code', 80)->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->timestamp('retry_after')->nullable();
            $table->timestamp('quota_deferred_at')->nullable();
            $table->timestamp('quota_resume_at')->nullable();
            $table->timestamp('local_deleted_at')->nullable();
            $table->foreignId('moderated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['mobile_user_id', 'client_upload_id'], 'goshen_experience_videos_mobile_upload_unique');
            $table->unique(['mobile_user_id', 'event_id', 'active_submission_key'], 'goshen_experience_videos_one_active_submission_unique');
            $table->index(['event_id', 'upload_status', 'moderation_status', 'created_at'], 'goshen_experience_videos_feed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_experience_videos');
    }
};
