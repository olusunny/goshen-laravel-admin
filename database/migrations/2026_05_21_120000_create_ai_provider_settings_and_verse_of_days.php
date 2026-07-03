<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider', 40)->default('openai')->index();
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->string('model')->default('gpt-5.4-mini');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedTinyInteger('timeout_seconds')->default(20);
            $table->decimal('temperature', 3, 2)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_test_result')->nullable();
            $table->timestamps();
        });

        Schema::create('verse_of_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reference');
            $table->string('version', 40)->default('KJV');
            $table->text('text');
            $table->text('reflection')->nullable();
            $table->text('prayer')->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('prophetic_decrees', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('is_active')->index();
        });

        \App\Models\PropheticDecree::query()
            ->whereNull('expires_at')
            ->orderBy('id')
            ->each(function (\App\Models\PropheticDecree $decree): void {
                $decree->forceFill([
                    'expires_at' => ($decree->created_at ?? now())->copy()->addDay(),
                ])->save();
            });
    }

    public function down(): void
    {
        Schema::table('prophetic_decrees', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });

        Schema::dropIfExists('verse_of_days');
        Schema::dropIfExists('ai_provider_settings');
    }
};
