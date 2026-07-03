<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_wallet_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('goshen_wallets')->cascadeOnDelete();
            $table->string('status', 40)->default('active')->index();
            $table->string('label')->nullable();
            $table->string('currency', 3)->default('GBP');
            $table->decimal('target_amount', 12, 2);
            $table->timestamp('target_at')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'status']);
            $table->index(['wallet_id', 'is_primary']);
        });

        Role::query()->firstOrCreate(['name' => 'event_manager', 'guard_name' => 'mobile']);
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_wallet_goals');
    }
};
