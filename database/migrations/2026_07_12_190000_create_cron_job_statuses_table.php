<?php

use App\Support\AdminPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_job_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('expression')->nullable();
            $table->string('frequency_label')->nullable();
            $table->text('command')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('never_run')->index();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->timestamp('last_success_at')->nullable()->index();
            $table->timestamp('last_failed_at')->nullable();
            $table->unsignedInteger('last_runtime_ms')->nullable();
            $table->integer('last_exit_code')->nullable();
            $table->unsignedBigInteger('run_count')->default(0);
            $table->unsignedBigInteger('failure_count')->default(0);
            $table->text('last_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $permission = Permission::findOrCreate(AdminPermissions::CRON_MONITOR, 'web');

        Role::query()
            ->whereIn('name', ['super_admin', 'web_it_manager'])
            ->get()
            ->each(fn (Role $role): Role => $role->givePermissionTo($permission));
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_job_statuses');

        Permission::query()
            ->where('name', AdminPermissions::CRON_MONITOR)
            ->where('guard_name', 'web')
            ->delete();
    }
};
