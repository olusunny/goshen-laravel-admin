<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('featured_image')->nullable();
            $table->json('gallery_images')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('price_type')->default('per_night');
            $table->string('currency', 3)->default('NGN');
            $table->unsignedInteger('capacity')->default(1);
            $table->unsignedInteger('max_adults')->default(1);
            $table->unsignedInteger('max_children')->default(0);
            $table->boolean('children_allowed')->default(false);
            $table->unsignedInteger('max_stay_days')->default(1);
            $table->string('check_in_time')->nullable();
            $table->string('checkout_time')->nullable();
            $table->longText('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('accommodation_facilities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('accommodation_services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('accommodation_category_facility', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accommodation_category_id');
            $table->unsignedBigInteger('accommodation_facility_id');
            $table->foreign('accommodation_category_id', 'acc_cat_fac_cat_fk')->references('id')->on('accommodation_categories')->cascadeOnDelete();
            $table->foreign('accommodation_facility_id', 'acc_cat_fac_fac_fk')->references('id')->on('accommodation_facilities')->cascadeOnDelete();
            $table->unique(['accommodation_category_id', 'accommodation_facility_id'], 'acc_cat_fac_unique');
        });

        Schema::create('accommodation_category_service', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accommodation_category_id');
            $table->unsignedBigInteger('accommodation_service_id');
            $table->foreign('accommodation_category_id', 'acc_cat_srv_cat_fk')->references('id')->on('accommodation_categories')->cascadeOnDelete();
            $table->foreign('accommodation_service_id', 'acc_cat_srv_srv_fk')->references('id')->on('accommodation_services')->cascadeOnDelete();
            $table->unique(['accommodation_category_id', 'accommodation_service_id'], 'acc_cat_srv_unique');
        });

        Schema::create('accommodation_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_category_id')->constrained()->cascadeOnDelete();
            $table->string('unit_name');
            $table->string('unit_number')->nullable();
            $table->string('status')->default('available')->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('accommodation_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accommodation_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['accommodation_category_id', 'start_date', 'end_date'], 'acc_blocked_lookup');
        });

        Schema::create('accommodation_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference')->unique();
            $table->foreignId('user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->foreignId('accommodation_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('accommodation_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->date('check_in_date');
            $table->date('checkout_date');
            $table->unsignedInteger('nights');
            $table->unsignedInteger('adults')->default(1);
            $table->unsignedInteger('children')->default(0);
            $table->unsignedInteger('total_occupants')->default(1);
            $table->decimal('price_per_night', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('booking_status')->default('pending_payment')->index();
            $table->string('payment_status')->default('pending')->index();
            $table->string('check_in_status')->default('pending')->index();
            $table->string('checkout_status')->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('rules_accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['accommodation_category_id', 'check_in_date', 'checkout_date'], 'acc_booking_lookup');
        });

        Schema::create('accommodation_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('accommodation_bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('mobile_users')->cascadeOnDelete();
            $table->string('payment_gateway')->default('paystack');
            $table->string('paystack_reference')->unique();
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('status')->default('pending')->index();
            $table->string('channel')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_payments');
        Schema::dropIfExists('accommodation_bookings');
        Schema::dropIfExists('accommodation_blocked_dates');
        Schema::dropIfExists('accommodation_units');
        Schema::dropIfExists('accommodation_category_service');
        Schema::dropIfExists('accommodation_category_facility');
        Schema::dropIfExists('accommodation_services');
        Schema::dropIfExists('accommodation_facilities');
        Schema::dropIfExists('accommodation_categories');
    }
};
