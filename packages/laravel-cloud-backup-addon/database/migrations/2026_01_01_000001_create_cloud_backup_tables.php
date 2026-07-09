<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cloud_backup_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('provider', 32);
            $table->string('owner_name')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('folder_path')->nullable();
            $table->longText('token_payload')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['provider', 'connected_at']);
        });

        Schema::create('cloud_backup_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('cloud_backup_connections')->cascadeOnDelete();
            $table->string('name');
            $table->string('frequency', 32)->default('daily');
            $table->boolean('enabled')->default(true);
            $table->boolean('include_files')->default(true);
            $table->boolean('include_database')->default(true);
            $table->string('source_path')->nullable();
            $table->string('database_connection')->nullable();
            $table->json('exclude_paths')->nullable();
            $table->unsignedInteger('retention_count')->default(7);
            $table->time('schedule_time')->default('00:30');
            $table->unsignedTinyInteger('schedule_weekday')->default(0);
            $table->unsignedTinyInteger('schedule_month_day')->default(1);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->timestamps();
            $table->index(['enabled', 'next_run_at']);
        });

        Schema::create('cloud_backup_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('connection_id')->constrained('cloud_backup_connections')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('cloud_backup_schedules')->nullOnDelete();
            $table->string('status', 32)->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('bytes_uploaded')->default(0);
            $table->json('manifest')->nullable();
            $table->longText('log')->nullable();
            $table->text('error_summary')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('cloud_backup_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('cloud_backup_runs')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('filename');
            $table->string('local_path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 128)->nullable();
            $table->string('remote_path')->nullable();
            $table->string('remote_id')->nullable();
            $table->string('status', 32)->default('created');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_backup_artifacts');
        Schema::dropIfExists('cloud_backup_runs');
        Schema::dropIfExists('cloud_backup_schedules');
        Schema::dropIfExists('cloud_backup_connections');
    }
};
