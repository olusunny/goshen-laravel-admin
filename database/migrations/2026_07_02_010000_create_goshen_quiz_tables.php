<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_quiz_celebration_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('image_paths')->nullable();
            $table->string('video_path')->nullable();
            $table->text('image_generation_prompt')->nullable();
            $table->text('remotion_prompt')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('goshen_quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('ei_events')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('celebration_media_id')->nullable()->constrained('goshen_quiz_celebration_media')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('start_instructions')->nullable();
            $table->text('completion_message')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->string('audience')->default('all_users')->index();
            $table->boolean('auto_grade')->default(true);
            $table->boolean('auto_select_winners')->default(true);
            $table->boolean('track_timing')->default(true);
            $table->unsignedInteger('timer_seconds')->default(300);
            $table->unsignedSmallInteger('winners_count')->default(1);
            $table->boolean('show_prize')->default(false);
            $table->string('prize_label')->nullable();
            $table->boolean('wallet_prize_enabled')->default(false);
            $table->decimal('wallet_prize_amount', 12, 2)->nullable();
            $table->string('wallet_prize_currency', 3)->default('GBP');
            $table->boolean('show_winners_immediately')->default(false);
            $table->boolean('celebration_enabled')->default(false);
            $table->timestamp('opens_at')->nullable()->index();
            $table->timestamp('closes_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'is_active']);
        });

        Schema::create('goshen_quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('goshen_quizzes')->cascadeOnDelete();
            $table->string('prompt');
            $table->string('type')->default('single_choice');
            $table->json('options')->nullable();
            $table->decimal('points', 8, 2)->default(1);
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('explanation')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'sort_order']);
        });

        Schema::create('goshen_quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('goshen_quizzes')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('ei_events')->nullOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('ei_bookings')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('ei_tickets')->nullOnDelete();
            $table->string('status')->default('started')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->timestamp('timed_out_at')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->decimal('max_score', 10, 2)->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('answered_count')->default(0);
            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->unsignedInteger('elapsed_seconds')->nullable();
            $table->json('answers')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'mobile_user_id'], 'goshen_quiz_unique_attempt');
            $table->index(['quiz_id', 'score', 'elapsed_seconds']);
        });

        Schema::create('goshen_quiz_winners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained('goshen_quizzes')->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('goshen_quiz_attempts')->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->decimal('score', 10, 2)->nullable();
            $table->unsignedInteger('elapsed_seconds')->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->string('prize_label')->nullable();
            $table->decimal('wallet_prize_amount', 12, 2)->nullable();
            $table->string('wallet_prize_currency', 3)->default('GBP');
            $table->string('wallet_prize_status')->default('not_configured')->index();
            $table->foreignId('wallet_sponsor_mobile_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->foreignId('wallet_ledger_entry_id')->nullable()->constrained('goshen_wallet_ledger_entries')->nullOnDelete();
            $table->string('wallet_transfer_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'rank'], 'goshen_quiz_unique_winner_rank');
            $table->unique(['quiz_id', 'mobile_user_id'], 'goshen_quiz_unique_winner_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_quiz_winners');
        Schema::dropIfExists('goshen_quiz_attempts');
        Schema::dropIfExists('goshen_quiz_questions');
        Schema::dropIfExists('goshen_quizzes');
        Schema::dropIfExists('goshen_quiz_celebration_media');
    }
};
