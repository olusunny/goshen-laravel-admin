<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_wallet_verification_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->string('email');
            $table->string('purpose', 80);
            $table->char('context_fingerprint', 64);
            $table->json('context');
            $table->string('code_hash');
            $table->string('status', 32)->default('sending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('send_count')->default(0);
            $table->string('created_ip', 45)->nullable();
            $table->string('last_sent_ip', 45)->nullable();
            $table->string('last_failed_ip', 45)->nullable();
            $table->string('consumed_ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'mobile_user_id', 'purpose', 'status'], 'web_wallet_challenge_actor_status');
            $table->index(['email', 'last_sent_at'], 'web_wallet_challenge_email_sent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_wallet_verification_challenges');
    }
};
