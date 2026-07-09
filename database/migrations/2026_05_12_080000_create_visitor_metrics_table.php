<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('session_key')->nullable()->index();
            $table->string('ip_hash', 64)->nullable()->index();
            $table->string('path')->index();
            $table->string('endpoint')->nullable()->index();
            $table->string('channel')->default('web')->index();
            $table->string('country')->default('Unknown')->index();
            $table->string('region')->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->string('content_type')->nullable()->index();
            $table->unsignedBigInteger('content_id')->nullable()->index();
            $table->unsignedInteger('visits')->default(1);
            $table->unsignedInteger('consumptions')->default(0);
            $table->text('user_agent')->nullable();
            $table->timestamp('visited_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_metrics');
    }
};
