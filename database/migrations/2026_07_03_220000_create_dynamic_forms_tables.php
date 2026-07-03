<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_forms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->string('visibility', 40)->default('public')->index();
            $table->boolean('one_submission_per_user')->default(false);
            $table->unsignedInteger('max_submissions')->nullable();
            $table->string('payment_type', 40)->default('free')->index();
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('GBP');
            $table->boolean('allow_stripe')->default(true);
            $table->boolean('allow_wallet')->default(true);
            $table->timestamp('opens_at')->nullable()->index();
            $table->timestamp('closes_at')->nullable()->index();
            $table->string('submit_button_label', 80)->default('Submit');
            $table->text('thank_you_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'visibility', 'opens_at', 'closes_at'], 'dynamic_forms_open_lookup');
        });

        Schema::create('dynamic_form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dynamic_form_id')->constrained('dynamic_forms')->cascadeOnDelete();
            $table->string('key', 120);
            $table->string('label', 180);
            $table->string('type', 40)->default('text');
            $table->string('placeholder', 180)->nullable();
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('settings')->nullable();
            $table->json('conditional_logic')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['dynamic_form_id', 'key'], 'dynamic_form_fields_form_key_unique');
            $table->index(['dynamic_form_id', 'sort_order'], 'dynamic_form_fields_order');
        });

        Schema::create('dynamic_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dynamic_form_id')->constrained('dynamic_forms')->cascadeOnDelete();
            $table->foreignId('mobile_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->foreignId('wallet_ledger_entry_id')->nullable()->constrained('goshen_wallet_ledger_entries')->nullOnDelete();
            $table->string('reference', 80)->unique();
            $table->string('name', 180)->nullable();
            $table->string('email', 180)->nullable()->index();
            $table->string('phone', 80)->nullable();
            $table->json('answers')->nullable();
            $table->string('status', 40)->default('submitted')->index();
            $table->string('payment_status', 40)->default('not_required')->index();
            $table->string('payment_provider', 40)->nullable()->index();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('provider_reference', 120)->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['dynamic_form_id', 'mobile_user_id'], 'dynamic_form_submissions_user_lookup');
            $table->index(['dynamic_form_id', 'status', 'payment_status'], 'dynamic_form_submissions_status_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_form_submissions');
        Schema::dropIfExists('dynamic_form_fields');
        Schema::dropIfExists('dynamic_forms');
    }
};
