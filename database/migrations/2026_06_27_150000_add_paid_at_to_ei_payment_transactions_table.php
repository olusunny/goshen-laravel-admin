<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ei_payment_transactions', 'paid_at')) {
            Schema::table('ei_payment_transactions', function (Blueprint $table): void {
                $table->timestamp('paid_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ei_payment_transactions', 'paid_at')) {
            Schema::table('ei_payment_transactions', function (Blueprint $table): void {
                $table->dropColumn('paid_at');
            });
        }
    }
};
