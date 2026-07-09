<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ei_bookings', function (Blueprint $table) {
            $table->string('payment_customer_id')->nullable()->after('customer_phone')->index();
            $table->string('payment_method_id')->nullable()->after('payment_customer_id');
            $table->boolean('auto_charge_enabled')->default(false)->after('payment_method_id')->index();
            $table->timestamp('auto_charge_failed_at')->nullable()->after('auto_charge_enabled');
            $table->text('auto_charge_failure_reason')->nullable()->after('auto_charge_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ei_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_customer_id',
                'payment_method_id',
                'auto_charge_enabled',
                'auto_charge_failed_at',
                'auto_charge_failure_reason',
            ]);
        });
    }
};
