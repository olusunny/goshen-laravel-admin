<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_group_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->text('message')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['church_group_id', 'mobile_user_id', 'status'], 'church_group_join_unique_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_group_join_requests');
    }
};
