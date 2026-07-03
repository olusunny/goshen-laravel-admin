<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodation_categories', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('gallery_images');
            $table->string('video_url')->nullable()->after('video_path');
            $table->string('video_title')->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('accommodation_categories', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'video_url', 'video_title']);
        });
    }
};
