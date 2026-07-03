<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->string('source_type')->default('upload')->after('source');
            $table->string('video_type')->nullable()->after('source_type');
            $table->string('hd_source')->nullable()->after('video_type');
            $table->string('sd_source')->nullable()->after('hd_source');
            $table->string('audio_source')->nullable()->after('sd_source');
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropColumn([
                'source_type',
                'video_type',
                'hd_source',
                'sd_source',
                'audio_source',
            ]);
        });
    }
};
