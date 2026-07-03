<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ei_events', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('owner_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('single');
            $table->text('description')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('venue_name')->nullable();
            $table->text('venue_address')->nullable();
            $table->string('support_email')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('sales_start_at')->nullable();
            $table->timestamp('sales_end_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ei_event_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number')->default(1);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'day_number']);
            $table->index(['event_id', 'starts_at']);
        });

        Schema::create('ei_event_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('currency', 3);
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('min_per_booking')->default(1);
            $table->unsignedInteger('max_per_booking')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'is_active']);
        });

        Schema::create('ei_event_attendee_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('text');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['event_id', 'key']);
        });

        Schema::create('ei_payment_plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->string('name');
            $table->string('currency', 3);
            $table->string('deposit_type')->default('percentage');
            $table->decimal('deposit_value', 12, 2);
            $table->unsignedSmallInteger('installment_count')->default(1);
            $table->unsignedSmallInteger('interval_days')->default(30);
            $table->unsignedSmallInteger('grace_days')->default(3);
            $table->string('ticket_issue_policy')->default('paid_in_full');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'is_active']);
        });

        Schema::create('ei_bookings', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('event_id')->constrained('ei_events')->restrictOnDelete();
            $table->foreignId('payment_plan_id')->nullable()->constrained('ei_payment_plans')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->index();
            $table->string('customer_phone')->nullable();
            $table->string('currency', 3);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->string('status')->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_id', 'status']);
        });

        Schema::create('ei_booking_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('ei_bookings')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ei_event_ticket_types')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('currency', 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ei_attendees', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->constrained('ei_bookings')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ei_event_ticket_types')->restrictOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('designation')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
        });

        Schema::create('ei_tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('event_id')->constrained('ei_events')->restrictOnDelete();
            $table->foreignId('booking_id')->constrained('ei_bookings')->cascadeOnDelete();
            $table->foreignId('attendee_id')->nullable()->constrained('ei_attendees')->nullOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ei_event_ticket_types')->restrictOnDelete();
            $table->string('ticket_number');
            $table->string('formatted_number')->nullable();
            $table->string('qr_hash', 128)->unique();
            $table->string('status')->default('not_checked_in')->index();
            $table->json('multiday_status')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'ticket_number']);
            $table->index(['event_id', 'status']);
        });

        Schema::create('ei_ticket_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ei_tickets')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('ei_events')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->index();
            $table->unsignedSmallInteger('day_number')->default(1);
            $table->string('status');
            $table->timestamp('checked_in_at');
            $table->string('source')->default('api');
            $table->string('device_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'checked_in_at']);
            $table->index(['ticket_id', 'day_number']);
        });

        Schema::create('ei_payment_installments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->constrained('ei_bookings')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('due_on');
            $table->timestamp('paid_at')->nullable();
            $table->string('status')->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'sequence']);
        });

        Schema::create('ei_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('booking_id')->constrained('ei_bookings')->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('ei_payment_installments')->nullOnDelete();
            $table->string('gateway');
            $table->string('provider_reference')->nullable();
            $table->string('provider_event_id')->nullable();
            $table->string('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending')->index();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'provider_reference']);
            $table->index(['gateway', 'provider_event_id']);
        });

        Schema::create('ei_payment_gateway_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('provider_event_id');
            $table->string('event_type');
            $table->string('status')->default('received');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'provider_event_id'], 'ei_webhook_gateway_event_unique');
        });

        Schema::create('ei_ticket_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ei_tickets')->cascadeOnDelete();
            $table->string('type');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'type']);
        });

        Schema::create('ei_ticket_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ei_tickets')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('ei_bookings')->nullOnDelete();
            $table->string('recipient');
            $table->string('subject');
            $table->string('status')->default('pending');
            $table->json('attachments')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'status']);
            $table->index(['recipient', 'created_at']);
        });

        Schema::create('ei_event_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained('ei_events')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->index();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ei_event_audit_logs');
        Schema::dropIfExists('ei_ticket_email_logs');
        Schema::dropIfExists('ei_ticket_documents');
        Schema::dropIfExists('ei_payment_gateway_webhook_events');
        Schema::dropIfExists('ei_payment_transactions');
        Schema::dropIfExists('ei_payment_installments');
        Schema::dropIfExists('ei_ticket_check_ins');
        Schema::dropIfExists('ei_tickets');
        Schema::dropIfExists('ei_attendees');
        Schema::dropIfExists('ei_booking_lines');
        Schema::dropIfExists('ei_bookings');
        Schema::dropIfExists('ei_payment_plans');
        Schema::dropIfExists('ei_event_attendee_fields');
        Schema::dropIfExists('ei_event_ticket_types');
        Schema::dropIfExists('ei_event_schedules');
        Schema::dropIfExists('ei_events');
    }
};
