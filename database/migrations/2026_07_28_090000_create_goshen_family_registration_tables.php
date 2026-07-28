<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goshen_families', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->unique()->constrained('ei_bookings')->nullOnDelete();
            $table->foreignId('created_by_mobile_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('goshen_family_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goshen_family_id')->constrained('goshen_families')->cascadeOnDelete();
            $table->foreignId('attendee_id')->nullable()->unique()->constrained('ei_attendees')->nullOnDelete();
            $table->foreignId('mobile_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->string('role', 20);
            $table->date('date_of_birth')->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['goshen_family_id', 'role']);
            $table->index('mobile_user_id');
        });

        Schema::table('mobile_users', function (Blueprint $table): void {
            $table->timestamp('adult_confirmed_at')->nullable()->after('birthday_day');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_users', function (Blueprint $table): void {
            $table->dropColumn('adult_confirmed_at');
        });

        Schema::dropIfExists('goshen_family_members');
        Schema::dropIfExists('goshen_families');
    }
};
