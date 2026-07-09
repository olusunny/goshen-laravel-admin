<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_user_id')->unique()->constrained('mobile_users')->cascadeOnDelete();
            $table->string('currency', 3)->default('GBP');
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_payment_method_id')->nullable();
            $table->decimal('goal_amount', 12, 2)->nullable();
            $table->string('goal_label')->nullable();
            $table->timestamp('goal_target_at')->nullable();
            $table->timestamps();
        });

        Schema::create('goshen_wallet_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('goshen_wallets')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('status', 40)->default('pending');
            $table->string('currency', 3)->default('GBP');
            $table->decimal('amount', 12, 2);
            $table->string('gateway')->nullable();
            $table->string('provider_reference')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('goshen_wallet_savings_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('goshen_wallets')->cascadeOnDelete();
            $table->string('status', 40)->default('active')->index();
            $table->string('frequency', 20)->default('weekly');
            $table->unsignedInteger('interval_count')->default(1);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('GBP');
            $table->unsignedInteger('remaining_cycles')->nullable();
            $table->timestamp('next_charge_at')->nullable()->index();
            $table->timestamp('last_charge_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_wallet_savings_plans');
        Schema::dropIfExists('goshen_wallet_ledger_entries');
        Schema::dropIfExists('goshen_wallets');
    }
};
