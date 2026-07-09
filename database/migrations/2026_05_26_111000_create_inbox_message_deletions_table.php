<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_message_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->timestamp('deleted_at')->useCurrent();

            $table->unique(['inbox_message_id', 'mobile_user_id'], 'inbox_delete_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_message_deletions');
    }
};
