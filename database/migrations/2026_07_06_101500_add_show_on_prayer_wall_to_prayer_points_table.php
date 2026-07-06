<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('prayer_points', 'show_on_prayer_wall')) {
            Schema::table('prayer_points', function (Blueprint $table): void {
                $table->boolean('show_on_prayer_wall')->default(true)->after('is_published')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prayer_points', 'show_on_prayer_wall')) {
            Schema::table('prayer_points', function (Blueprint $table): void {
                $table->dropIndex(['show_on_prayer_wall']);
                $table->dropColumn('show_on_prayer_wall');
            });
        }
    }
};
