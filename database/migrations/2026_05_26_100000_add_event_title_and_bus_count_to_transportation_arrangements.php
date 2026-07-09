<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportation_arrangements', function (Blueprint $table) {
            $table->string('event_title')->nullable()->after('program_name');
            $table->unsignedSmallInteger('buses_available')->default(1)->after('passenger_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('transportation_arrangements', function (Blueprint $table) {
            $table->dropColumn(['event_title', 'buses_available']);
        });
    }
};
