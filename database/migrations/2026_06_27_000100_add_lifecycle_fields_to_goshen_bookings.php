<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ei_bookings', function (Blueprint $table): void {
            if (! Schema::hasColumn('ei_bookings', 'payment_expires_at')) {
                $table->timestamp('payment_expires_at')->nullable()->after('status')->index();
            }

            if (! Schema::hasColumn('ei_bookings', 'payment_reminder_sent_at')) {
                $table->timestamp('payment_reminder_sent_at')->nullable()->after('payment_expires_at');
            }

            if (! Schema::hasColumn('ei_bookings', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('payment_reminder_sent_at')->index();
            }

            if (! Schema::hasColumn('ei_bookings', 'cancelled_by_id')) {
                $table->foreignId('cancelled_by_id')->nullable()->after('cancelled_at')->index();
            }

            if (! Schema::hasColumn('ei_bookings', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ei_bookings', function (Blueprint $table): void {
            foreach ([
                'cancellation_reason',
                'cancelled_by_id',
                'cancelled_at',
                'payment_reminder_sent_at',
                'payment_expires_at',
            ] as $column) {
                if (Schema::hasColumn('ei_bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
