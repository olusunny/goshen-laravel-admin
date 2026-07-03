<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table): void {
            $table->id();
            $table->string('package_key')->unique();
            $table->string('composer_name')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('installed_version')->nullable();
            $table->string('available_version')->nullable();
            $table->string('status', 40)->default('uploaded')->index();
            $table->string('provider_class')->nullable();
            $table->string('namespace')->nullable();
            $table->json('autoload_psr4')->nullable();
            $table->json('manifest');
            $table->string('install_path')->nullable();
            $table->string('uploaded_zip_path')->nullable();
            $table->string('checksum', 128)->nullable()->index();
            $table->boolean('signature_verified')->nullable();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('uninstalled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('last_health_status', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('addon_install_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('addon_id')->nullable()->constrained('addons')->nullOnDelete();
            $table->string('package_key')->nullable()->index();
            $table->string('action', 40)->index();
            $table->string('status', 40)->default('pending')->index();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->longText('output')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('addon_update_backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
            $table->string('from_version')->nullable();
            $table->string('to_version')->nullable();
            $table->string('backup_path');
            $table->string('database_backup_reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_update_backups');
        Schema::dropIfExists('addon_install_logs');
        Schema::dropIfExists('addons');
    }
};
