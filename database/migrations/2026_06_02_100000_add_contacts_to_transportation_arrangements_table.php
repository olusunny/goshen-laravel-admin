<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transportation_arrangements', function (Blueprint $table) {
            if (! Schema::hasColumn('transportation_arrangements', 'contacts')) {
                $table->json('contacts')->nullable()->after('contact_person_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transportation_arrangements', function (Blueprint $table) {
            if (Schema::hasColumn('transportation_arrangements', 'contacts')) {
                $table->dropColumn('contacts');
            }
        });
    }
};
