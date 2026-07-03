<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_users', function (Blueprint $table): void {
            $table->boolean('wallet_security_reset_required')
                ->default(false)
                ->index();
            $table->timestamp('wallet_security_reset_requested_at')
                ->nullable()
                ->index();
            $table->timestamp('wallet_security_reset_acknowledged_at')
                ->nullable();
        });

        Schema::create('wallet_security_reset_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_user_id')
                ->constrained('mobile_users')
                ->cascadeOnDelete();
            $table->foreignId('admin_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->string('verification_method', 80);
            $table->text('verification_notes')->nullable();
            $table->boolean('invalidated_mobile_session')->default(false);
            $table->boolean('notified_user')->default(false);
            $table->timestamp('requested_at')->useCurrent()->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_ip', 45)->nullable();
            $table->string('acknowledged_user_agent', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['mobile_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_security_reset_requests');

        Schema::table('mobile_users', function (Blueprint $table): void {
            $table->dropColumn([
                'wallet_security_reset_required',
                'wallet_security_reset_requested_at',
                'wallet_security_reset_acknowledged_at',
            ]);
        });
    }
};
