<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transportation_arrangements')) {
            return;
        }

        Schema::create('transportation_arrangements', function (Blueprint $table) {
            $table->id();
            $table->string('program_name')->default('72Hours');
            $table->string('city_town');
            $table->string('state')->nullable();
            $table->text('bus_location');
            $table->string('bus_type')->nullable();
            $table->unsignedSmallInteger('passenger_capacity')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('program_name', 'transport_program_idx');
            $table->index('is_active', 'transport_active_idx');
            $table->index('sort_order', 'transport_sort_idx');
            $table->index(['program_name', 'is_active', 'sort_order'], 'transport_program_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportation_arrangements');
    }
};
