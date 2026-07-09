<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_events', function (Blueprint $table) {
            $table->string('theme')->nullable()->after('venue');
            $table->string('bible_verse')->nullable()->after('theme');
            $table->string('host')->nullable()->after('bible_verse');
            $table->text('other_ministers')->nullable()->after('host');
            $table->json('live_streaming_platforms')->nullable()->after('other_ministers');
            $table->json('invited_gospel_musicians')->nullable()->after('live_streaming_platforms');
        });
    }

    public function down(): void
    {
        Schema::table('church_events', function (Blueprint $table) {
            $table->dropColumn([
                'theme',
                'bible_verse',
                'host',
                'other_ministers',
                'live_streaming_platforms',
                'invited_gospel_musicians',
            ]);
        });
    }
};
