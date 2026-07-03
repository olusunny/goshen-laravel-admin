<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prophetic_decrees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('go_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->string('title')->default('Prophetic Decree');
            $table->string('audio_path');
            $table->unsignedSmallInteger('duration')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['is_active', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prophetic_decrees');
    }
};
