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
            if (! Schema::hasColumn('cloud_backup_runs', 'backup_name')) {
                $table->string('backup_name')->nullable()->after('schedule_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cloud_backup_runs')) {
            return;
        }

        Schema::table('cloud_backup_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('cloud_backup_runs', 'backup_name')) {
                $table->dropColumn('backup_name');
            }
        });
    }
};
