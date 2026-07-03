<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_events', function (Blueprint $table) {
            $table->string('registration_url')->nullable()->after('thumbnail');
            $table->string('registration_availability')->default('everywhere')->after('registration_url');
        });

        Schema::table('content_pages', function (Blueprint $table) {
            $table->string('hero_image')->nullable()->after('body');
            $table->json('sections')->nullable()->after('hero_image');
        });
    }

    public function down(): void
    {
        Schema::table('church_events', function (Blueprint $table) {
            $table->dropColumn(['registration_url', 'registration_availability']);
        });

        Schema::table('content_pages', function (Blueprint $table) {
            $table->dropColumn(['hero_image', 'sections']);
        });
    }
};
