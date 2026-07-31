<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $create = static function (string $table, callable $callback): void {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $callback);
            }
        };

        $create('birthday_celebration_verses', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 120);
            $table->string('body', 500);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        $create('birthday_celebration_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_user_id')->unique()->constrained('mobile_users')->cascadeOnDelete();
            $table->boolean('visibility_enabled')->default(true)->index();
            $table->boolean('greetings_enabled')->default(true);
            $table->boolean('use_profile_photo')->default(true);
            $table->string('preferred_name', 120)->nullable();
            $table->foreignId('preferred_verse_id')->nullable()->constrained('birthday_celebration_verses')->restrictOnDelete();
            $table->timestamps();
        });

        $create('birthday_celebration_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_default')->default(false)->index();
            $table->string('background_color', 7)->default('#4A2E62');
            $table->string('accent_color', 7)->default('#D49A2A');
            $table->string('verse', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $create('birthday_celebration_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        $create('birthday_celebrations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('birthday_celebration_templates')->restrictOnDelete();
            $table->unsignedSmallInteger('birthday_year')->index();
            $table->date('birthday_date')->index();
            $table->string('status', 30)->default('preview_ready')->index();
            $table->timestamp('previewed_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('closes_at')->nullable()->index();
            $table->timestamp('purge_due_at')->nullable()->index();
            $table->timestamp('purged_at')->nullable()->index();
            $table->string('card_disk', 80)->nullable();
            $table->string('card_path')->nullable();
            $table->string('card_mime', 100)->nullable();
            $table->string('display_name', 120);
            $table->string('verse', 500)->nullable();
            $table->text('thank_you')->nullable();
            $table->timestamp('thank_you_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['mobile_user_id', 'birthday_year'], 'birthday_celebration_member_year_unique');
        });

        $create('birthday_celebration_greetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('celebration_id')->constrained('birthday_celebrations')->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->text('body');
            $table->string('idempotency_key', 120)->nullable();
            $table->string('status', 20)->default('visible')->index();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
            $table->unique(['celebration_id', 'mobile_user_id'], 'birthday_greeting_member_unique');
            $table->unique(['celebration_id', 'idempotency_key'], 'birthday_greeting_key_unique');
        });

        $create('birthday_celebration_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('celebration_id')->constrained('birthday_celebrations')->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->string('reaction', 30);
            $table->timestamps();
            $table->unique(['celebration_id', 'mobile_user_id'], 'birthday_reaction_member_unique');
        });

        $create('birthday_celebration_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('greeting_id')->constrained('birthday_celebration_greetings')->cascadeOnDelete();
            $table->foreignId('reporter_mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['greeting_id', 'reporter_mobile_user_id'], 'birthday_reporter_unique');
        });

        $create('birthday_celebration_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('celebration_id')->nullable()->constrained('birthday_celebrations')->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('dedupe_key', 160)->unique();
            $table->unsignedBigInteger('inbox_message_id')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        $create('birthday_celebration_correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->unsignedTinyInteger('birthday_month');
            $table->unsignedTinyInteger('birthday_day');
            $table->string('reason', 500)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
            $table->index(['mobile_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_celebration_correction_requests');
        Schema::dropIfExists('birthday_celebration_deliveries');
        Schema::dropIfExists('birthday_celebration_reports');
        Schema::dropIfExists('birthday_celebration_reactions');
        Schema::dropIfExists('birthday_celebration_greetings');
        Schema::dropIfExists('birthday_celebrations');
        Schema::dropIfExists('birthday_celebration_settings');
        Schema::dropIfExists('birthday_celebration_preferences');
        Schema::dropIfExists('birthday_celebration_verses');
        Schema::dropIfExists('birthday_celebration_templates');
    }
};
