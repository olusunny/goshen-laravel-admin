<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cloud_backup_oauth_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('tenant')->nullable();
            $table->string('redirect_uri')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_backup_oauth_settings');
    }
};
