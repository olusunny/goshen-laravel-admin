<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ei_ticket_check_ins', function (Blueprint $table) {
            $table->unique(['ticket_id', 'day_number'], 'ei_ticket_check_ins_ticket_day_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ei_ticket_check_ins', function (Blueprint $table) {
            $table->dropUnique('ei_ticket_check_ins_ticket_day_unique');
        });
    }
};
