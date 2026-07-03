<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_prayer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('text');
            $table->text('text')->nullable();
            $table->string('audio_path')->nullable();
            $table->unsignedSmallInteger('audio_duration_seconds')->nullable();
            $table->boolean('is_anonymous')->default(true)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('hidden_at')->nullable()->index();
            $table->string('hidden_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->unsignedSmallInteger('flags_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamps();

            $table->index(['hidden_at', 'expires_at']);
            $table->index(['mobile_user_id', 'created_at']);
        });

        Schema::create('community_prayer_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_prayer_request_id');
            $table->foreign('community_prayer_request_id', 'cp_comments_request_fk')
                ->references('id')
                ->on('community_prayer_requests')
                ->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('source', 20)->default('manual');
            $table->string('preset_key')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->timestamp('hidden_at')->nullable()->index();
            $table->string('hidden_reason')->nullable();
            $table->timestamps();

            $table->index(['community_prayer_request_id', 'created_at'], 'community_prayer_comments_request_created_idx');
        });

        Schema::create('community_prayer_request_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_prayer_request_id');
            $table->foreign('community_prayer_request_id', 'cp_flags_request_fk')
                ->references('id')
                ->on('community_prayer_requests')
                ->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 80);
            $table->text('details')->nullable();
            $table->timestamps();

            $table->unique(['community_prayer_request_id', 'mobile_user_id'], 'community_prayer_unique_flag');
        });

        Schema::create('community_prayer_comment_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_prayer_request_id');
            $table->foreign('community_prayer_request_id', 'cp_suggestions_request_fk')
                ->references('id')
                ->on('community_prayer_requests')
                ->cascadeOnDelete();
            $table->string('source', 20)->default('preset');
            $table->string('preset_key')->nullable();
            $table->text('text');
            $table->timestamps();
        });

        Schema::create('community_prayer_ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('community_prayer_request_id')->nullable();
            $table->foreign('community_prayer_request_id', 'cp_ai_logs_request_fk')
                ->references('id')
                ->on('community_prayer_requests')
                ->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('input_hash', 64)->index();
            $table->json('output')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_prayer_ai_logs');
        Schema::dropIfExists('community_prayer_comment_suggestions');
        Schema::dropIfExists('community_prayer_request_flags');
        Schema::dropIfExists('community_prayer_request_comments');
        Schema::dropIfExists('community_prayer_requests');
    }
};
