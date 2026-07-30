<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_retreat_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->string('label');
            $table->string('file_path');
            $table->string('filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_retreat_materials');
    }
};
