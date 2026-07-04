<?php

use App\Models\ChurchEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_events', function (Blueprint $table) {
            if (! Schema::hasColumn('church_events', 'recurrence_type')) {
                $table->string('recurrence_type', 40)
                    ->default(ChurchEvent::RECURRENCE_NONE)
                    ->after('ends_at')
                    ->index();
            }

            if (! Schema::hasColumn('church_events', 'recurrence_interval')) {
                $table->unsignedTinyInteger('recurrence_interval')->default(1)->after('recurrence_type');
            }

            if (! Schema::hasColumn('church_events', 'recurrence_weekday')) {
                $table->unsignedTinyInteger('recurrence_weekday')->nullable()->after('recurrence_interval');
            }

            if (! Schema::hasColumn('church_events', 'recurrence_week_of_month')) {
                $table->smallInteger('recurrence_week_of_month')->nullable()->after('recurrence_weekday');
            }

            if (! Schema::hasColumn('church_events', 'recurrence_until')) {
                $table->date('recurrence_until')->nullable()->after('recurrence_week_of_month')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('church_events', function (Blueprint $table) {
            foreach ([
                'recurrence_until',
                'recurrence_week_of_month',
                'recurrence_weekday',
                'recurrence_interval',
                'recurrence_type',
            ] as $column) {
                if (Schema::hasColumn('church_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
