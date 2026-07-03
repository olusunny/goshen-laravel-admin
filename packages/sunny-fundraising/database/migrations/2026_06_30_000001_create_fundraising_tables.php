<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundraising_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('cause')->nullable();
            $table->string('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->decimal('goal_amount', 14, 2);
            $table->decimal('raised_amount', 14, 2)->default(0);
            $table->unsignedInteger('donor_count')->default(0);
            $table->string('currency', 3)->default('GBP');
            $table->string('status', 40)->default('draft')->index();
            $table->timestamp('start_at')->nullable()->index();
            $table->timestamp('end_at')->nullable()->index();
            $table->boolean('auto_stop_when_goal_reached')->default(true);
            $table->boolean('show_recent_contributors')->default(true);
            $table->string('feature_media_type', 40)->nullable();
            $table->unsignedBigInteger('feature_media_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('fundraising_campaign_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('fundraising_campaigns')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('disk')->default('public');
            $table->string('path')->nullable();
            $table->string('url')->nullable();
            $table->string('youtube_video_id', 32)->nullable();
            $table->string('title')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('fundraising_campaign_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('fundraising_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_type')->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('GBP');
            $table->string('status', 40)->default('pending')->index();
            $table->boolean('is_anonymous')->default(false);
            $table->string('display_name')->nullable();
            $table->text('message')->nullable();
            $table->string('wallet_transaction_id')->nullable()->index();
            $table->string('idempotency_key_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'user_id', 'user_type', 'idempotency_key_hash'], 'fundraising_contribution_idempotent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraising_campaign_contributions');
        Schema::dropIfExists('fundraising_campaign_media');
        Schema::dropIfExists('fundraising_campaigns');
    }
};
