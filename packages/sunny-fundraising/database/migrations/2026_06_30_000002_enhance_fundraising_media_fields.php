<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fundraising_campaign_media', function (Blueprint $table): void {
            if (! Schema::hasColumn('fundraising_campaign_media', 'thumbnail_path')) {
                $table->string('thumbnail_path')->nullable()->after('path');
            }

            if (! Schema::hasColumn('fundraising_campaign_media', 'mime_type')) {
                $table->string('mime_type', 120)->nullable()->after('youtube_video_id');
            }

            if (! Schema::hasColumn('fundraising_campaign_media', 'size')) {
                $table->unsignedBigInteger('size')->nullable()->after('mime_type');
            }

            if (! Schema::hasColumn('fundraising_campaign_media', 'is_feature')) {
                $table->boolean('is_feature')->default(false)->after('sort_order')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('fundraising_campaign_media', function (Blueprint $table): void {
            foreach (['thumbnail_path', 'mime_type', 'size', 'is_feature'] as $column) {
                if (Schema::hasColumn('fundraising_campaign_media', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
