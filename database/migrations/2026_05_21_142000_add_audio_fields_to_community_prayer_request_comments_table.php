<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_prayer_request_comments', function (Blueprint $table) {
            $table->text('text')->nullable()->change();
            $table->string('audio_path')->nullable()->after('text');
            $table->unsignedSmallInteger('audio_duration_seconds')->nullable()->after('audio_path');
        });
    }

    public function down(): void
    {
        Schema::table('community_prayer_request_comments', function (Blueprint $table) {
            $table->text('text')->nullable(false)->change();
            $table->dropColumn(['audio_path', 'audio_duration_seconds']);
        });
    }
};
