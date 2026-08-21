<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_youtube_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('channel_id')->nullable()->unique();
            $table->string('channel_title')->nullable();
            $table->json('scopes')->nullable();
            $table->string('default_privacy', 20)->default('private');
            $table->string('health', 40)->default('unconfigured')->index();
            $table->longText('refresh_token_payload')->nullable();
            $table->foreignId('connected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->string('last_error_message', 500)->nullable();
            $table->timestamp('quota_blocked_at')->nullable();
            $table->timestamp('quota_resume_at')->nullable()->index();
            $table->string('quota_error_code', 120)->nullable();
            $table->timestamp('quota_resumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_youtube_connections');
    }
};
