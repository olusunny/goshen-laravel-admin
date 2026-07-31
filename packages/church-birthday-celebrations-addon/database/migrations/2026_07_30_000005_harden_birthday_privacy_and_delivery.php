<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('birthday_celebration_preferences', 'managed_by_mobile_user_id')) {
            Schema::table('birthday_celebration_preferences', function (Blueprint $table): void {
                $table->dropColumn('managed_by_mobile_user_id');
            });
        }

        if (! Schema::hasColumn('birthday_celebration_preferences', 'preferred_template_id')) {
            Schema::table('birthday_celebration_preferences', function (Blueprint $table): void {
                $table->foreignId('preferred_template_id')->nullable()
                    ->constrained('birthday_celebration_templates')->restrictOnDelete();
            });
        }

        Schema::table('birthday_celebration_deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('birthday_celebration_deliveries', 'status')) {
                $table->string('status', 20)->default('pending')->index();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('last_attempt_at')->nullable();
                $table->text('last_error')->nullable();
            }
        });

        if (! Schema::hasTable('birthday_celebration_correction_requests')) {
            Schema::create('birthday_celebration_correction_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
                $table->unsignedTinyInteger('birthday_month');
                $table->unsignedTinyInteger('birthday_day');
                $table->string('reason', 500)->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->timestamps();
                $table->index(['mobile_user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_celebration_correction_requests');

        if (Schema::hasColumn('birthday_celebration_preferences', 'preferred_template_id')) {
            Schema::table('birthday_celebration_preferences', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('preferred_template_id');
            });
        }

        Schema::table('birthday_celebration_deliveries', function (Blueprint $table): void {
            foreach (['status', 'attempts', 'last_attempt_at', 'last_error'] as $column) {
                if (Schema::hasColumn('birthday_celebration_deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
