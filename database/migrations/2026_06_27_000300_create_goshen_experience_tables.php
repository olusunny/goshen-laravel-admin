<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_experience_surveys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('allow_audio')->default(true);
            $table->boolean('allow_video')->default(true);
            $table->boolean('reminder_enabled')->default(true);
            $table->unsignedSmallInteger('reminder_interval_minutes')->default(60);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->text('thank_you_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'is_active']);
        });

        Schema::create('goshen_experience_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_id')->constrained('goshen_experience_surveys')->cascadeOnDelete();
            $table->string('prompt');
            $table->string('type')->default('textarea');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['survey_id', 'sort_order']);
        });

        Schema::create('goshen_experience_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_id')->constrained('goshen_experience_surveys')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('ei_bookings')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('ei_tickets')->nullOnDelete();
            $table->text('story')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedSmallInteger('audio_duration_seconds')->nullable();
            $table->string('video_path')->nullable();
            $table->unsignedSmallInteger('video_duration_seconds')->nullable();
            $table->json('answers')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'mobile_user_id'], 'goshen_experience_unique_response');
            $table->index(['event_id', 'submitted_at']);
        });

        Schema::create('goshen_experience_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('survey_id')->constrained('goshen_experience_surveys')->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('sent_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'mobile_user_id'], 'goshen_experience_unique_reminder');
            $table->index(['completed_at', 'last_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_experience_reminders');
        Schema::dropIfExists('goshen_experience_responses');
        Schema::dropIfExists('goshen_experience_questions');
        Schema::dropIfExists('goshen_experience_surveys');
    }
};
