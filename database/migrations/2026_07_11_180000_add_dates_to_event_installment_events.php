<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ei_events', function (Blueprint $table): void {
            $table->date('start_date')->nullable()->after('sales_end_at')->index();
            $table->date('end_date')->nullable()->after('start_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ei_events', function (Blueprint $table): void {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
