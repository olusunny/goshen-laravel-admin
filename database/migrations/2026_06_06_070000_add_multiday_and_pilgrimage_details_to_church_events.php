<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_events', function (Blueprint $table) {
            $table->string('portrait_image')->nullable()->after('thumbnail');
            $table->json('event_schedule')->nullable()->after('ends_at');
            $table->boolean('is_pilgrimage')->default(false)->after('event_schedule');
            $table->json('pilgrimage_details')->nullable()->after('is_pilgrimage');
        });
    }

    public function down(): void
    {
        Schema::table('church_events', function (Blueprint $table) {
            $table->dropColumn([
                'portrait_image',
                'event_schedule',
                'is_pilgrimage',
                'pilgrimage_details',
            ]);
        });
    }
};
