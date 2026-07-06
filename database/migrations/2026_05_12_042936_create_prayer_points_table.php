<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prayer_points', function (Blueprint $table) {
            $table->id();
            $table->date('date')->nullable()->index();
            $table->string('title');
            $table->string('author')->nullable();
            $table->longText('content');
            $table->string('thumbnail')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('show_on_prayer_wall')->default(true)->index();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prayer_points');
    }
};
