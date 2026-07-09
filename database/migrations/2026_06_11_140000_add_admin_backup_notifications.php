<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cloud_backup_runs') && ! Schema::hasColumn('cloud_backup_runs', 'initiated_by_user_id')) {
            Schema::table('cloud_backup_runs', function (Blueprint $table): void {
                $table->foreignId('initiated_by_user_id')
                    ->nullable()
                    ->after('schedule_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cloud_backup_runs') && Schema::hasColumn('cloud_backup_runs', 'initiated_by_user_id')) {
            Schema::table('cloud_backup_runs', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('initiated_by_user_id');
            });
        }

        // Keep notification records on rollback to avoid deleting admin alert history.
    }
};
