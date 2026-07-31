<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('birthday_celebration_delivery_links', function (Blueprint $table): void {
            $table->foreignId('delivery_id')->constrained('birthday_celebration_deliveries')->cascadeOnDelete();
            $table->foreignId('celebration_id')->constrained('birthday_celebrations')->cascadeOnDelete();
            $table->primary(['delivery_id', 'celebration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_celebration_delivery_links');
    }
};
