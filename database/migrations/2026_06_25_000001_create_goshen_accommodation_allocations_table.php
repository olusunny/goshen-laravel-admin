<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('goshen_accommodation_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('ei_attendees')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('ei_tickets')->nullOnDelete();
            $table->string('status')->default('assigned')->index();
            $table->string('building')->nullable();
            $table->string('room')->nullable();
            $table->string('bed')->nullable();
            $table->string('check_in_note')->nullable();
            $table->json('attendee_visible_details')->nullable();
            $table->json('internal_notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'attendee_id']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goshen_accommodation_allocations');
    }
};
