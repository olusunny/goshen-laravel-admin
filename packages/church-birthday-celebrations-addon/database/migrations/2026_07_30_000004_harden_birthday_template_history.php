<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('birthday_celebration_templates', 'sort_order')) {
            Schema::table('birthday_celebration_templates', function (Blueprint $table): void {
                $table->unsignedInteger('sort_order')->default(0)->index()->after('name');
                $table->unsignedSmallInteger('version')->default(1)->after('sort_order');
                $table->boolean('is_default')->default(false)->index()->after('version');
            });
        }

        if (! Schema::hasTable('birthday_celebration_verses')) {
            Schema::create('birthday_celebration_verses', function (Blueprint $table): void {
                $table->id();
                $table->string('reference', 120);
                $table->string('body', 500);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('birthday_celebration_preferences', 'preferred_verse_id')) {
            Schema::table('birthday_celebration_preferences', function (Blueprint $table): void {
                $table->foreignId('preferred_verse_id')->nullable()->constrained('birthday_celebration_verses')->restrictOnDelete();
            });
        }

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('birthday_celebrations', function (Blueprint $table): void {
                $table->dropForeign(['template_id']);
                $table->foreign('template_id')->references('id')->on('birthday_celebration_templates')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('birthday_celebrations', function (Blueprint $table): void {
                $table->dropForeign(['template_id']);
                $table->foreign('template_id')->references('id')->on('birthday_celebration_templates')->nullOnDelete();
            });
        }
        if (Schema::hasColumn('birthday_celebration_preferences', 'preferred_verse_id')) {
            Schema::table('birthday_celebration_preferences', function (Blueprint $table): void {
                $table->dropForeign(['preferred_verse_id']);
                $table->dropColumn('preferred_verse_id');
            });
        }
        Schema::dropIfExists('birthday_celebration_verses');
        if (Schema::hasColumn('birthday_celebration_templates', 'sort_order')) {
            Schema::table('birthday_celebration_templates', function (Blueprint $table): void {
                $table->dropColumn(['sort_order', 'version', 'is_default']);
            });
        }
    }
};
