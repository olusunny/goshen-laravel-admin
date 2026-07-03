<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transportation_arrangements', 'buses_available')) {
            Schema::table('transportation_arrangements', function (Blueprint $table) {
                $table->unsignedSmallInteger('buses_available')->nullable()->default(null)->change();
            });

            DB::table('transportation_arrangements')
                ->where('program_name', '72Hours')
                ->where('event_title', '72Hours June Edition Transportation')
                ->update(['buses_available' => null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transportation_arrangements', 'buses_available')) {
            DB::table('transportation_arrangements')
                ->whereNull('buses_available')
                ->update(['buses_available' => 1]);

            Schema::table('transportation_arrangements', function (Blueprint $table) {
                $table->unsignedSmallInteger('buses_available')->default(1)->nullable(false)->change();
            });
        }
    }
};
