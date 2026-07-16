<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counseling_provider_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mobile_user_id')->nullable()->index();
            $table->unsignedBigInteger('admin_user_id')->nullable()->index();
            $table->string('display_name');
            $table->string('role', 80)->default('counselor')->index();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('timezone', 80)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('languages')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('counseling_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('requester_mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->foreignId('assigned_provider_profile_id')->nullable()->constrained('counseling_provider_profiles')->nullOnDelete();
            $table->string('status', 40)->default('submitted')->index();
            $table->string('priority', 40)->default('normal')->index();
            $table->string('category', 80)->nullable()->index();
            $table->string('subject')->nullable();
            $table->text('summary')->nullable();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('locale', 20)->nullable();
            $table->string('timezone', 80)->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->unsignedBigInteger('closed_by_id')->nullable();
            $table->string('closed_by_type')->nullable();
            $table->string('closure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('counseling_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('counseling_cases')->cascadeOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('direction', 40)->default('inbound')->index();
            $table->string('message_type', 40)->default('text')->index();
            $table->text('body')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->string('media_disk')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime', 120)->nullable();
            $table->unsignedInteger('media_size_bytes')->nullable();
            $table->unsignedInteger('media_duration_seconds')->nullable();
            $table->string('visibility', 40)->default('case_participants')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id'], 'counseling_messages_actor_index');
        });

        Schema::create('counseling_message_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('counseling_messages')->cascadeOnDelete();
            $table->unsignedBigInteger('recipient_id');
            $table->string('recipient_type');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'recipient_type', 'recipient_id'], 'counseling_message_receipt_unique');
        });

        Schema::create('counseling_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('counseling_cases')->cascadeOnDelete();
            $table->foreignId('provider_profile_id')->nullable()->constrained('counseling_provider_profiles')->nullOnDelete();
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->string('assignee_type')->nullable();
            $table->unsignedBigInteger('assigned_by_id')->nullable();
            $table->string('assigned_by_type')->nullable();
            $table->string('role', 80)->default('primary')->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['assignee_type', 'assignee_id'], 'counseling_assignments_assignee_index');
        });

        Schema::create('counseling_case_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('counseling_cases')->cascadeOnDelete();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_type')->nullable();
            $table->text('body');
            $table->boolean('is_encrypted')->default(false);
            $table->string('visibility', 40)->default('providers')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['author_type', 'author_id'], 'counseling_case_notes_author_index');
        });

        Schema::create('counseling_safeguarding_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('counseling_cases')->cascadeOnDelete();
            $table->unsignedBigInteger('reported_by_id')->nullable();
            $table->string('reported_by_type')->nullable();
            $table->string('event_type', 80)->index();
            $table->string('severity', 40)->default('review')->index();
            $table->text('summary')->nullable();
            $table->text('action_taken')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_id')->nullable();
            $table->string('resolved_by_type')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['reported_by_type', 'reported_by_id'], 'counseling_safeguarding_reporter_index');
        });

        Schema::create('counseling_case_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('counseling_cases')->cascadeOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('event_type', 80)->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id'], 'counseling_case_events_actor_index');
        });

        Schema::create('counseling_country_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code', 2)->index();
            $table->string('locale', 20)->nullable()->index();
            $table->string('name');
            $table->string('resource_type', 80)->index();
            $table->string('phone')->nullable();
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('review_after')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counseling_country_resources');
        Schema::dropIfExists('counseling_case_events');
        Schema::dropIfExists('counseling_safeguarding_events');
        Schema::dropIfExists('counseling_case_notes');
        Schema::dropIfExists('counseling_assignments');
        Schema::dropIfExists('counseling_message_receipts');
        Schema::dropIfExists('counseling_messages');
        Schema::dropIfExists('counseling_cases');
        Schema::dropIfExists('counseling_provider_profiles');
    }
};
