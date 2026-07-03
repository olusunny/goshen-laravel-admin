<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('cloud_backup_runs')) {
            return;
        }

        Schema::table('cloud_backup_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('cloud_backup_runs', 'progress_percent')) {
                $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            }

            if (! Schema::hasColumn('cloud_backup_runs', 'current_step')) {
                $table->string('current_step')->nullable()->after('progress_percent');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cloud_backup_runs')) {
            return;
        }

        Schema::table('cloud_backup_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('cloud_backup_runs', 'current_step')) {
                $table->dropColumn('current_step');
            }

            if (Schema::hasColumn('cloud_backup_runs', 'progress_percent')) {
                $table->dropColumn('progress_percent');
            }
        });
    }
};
