<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cloud_backup_schedules', function (Blueprint $table): void {
            if (! Schema::hasColumn('cloud_backup_schedules', 'schedule_time')) {
                $table->time('schedule_time')->default('00:30')->after('retention_count');
            }

            if (! Schema::hasColumn('cloud_backup_schedules', 'schedule_weekday')) {
                $table->unsignedTinyInteger('schedule_weekday')->default(0)->after('schedule_time');
            }

            if (! Schema::hasColumn('cloud_backup_schedules', 'schedule_month_day')) {
                $table->unsignedTinyInteger('schedule_month_day')->default(1)->after('schedule_weekday');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cloud_backup_schedules', function (Blueprint $table): void {
            foreach (['schedule_month_day', 'schedule_weekday', 'schedule_time'] as $column) {
                if (Schema::hasColumn('cloud_backup_schedules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
