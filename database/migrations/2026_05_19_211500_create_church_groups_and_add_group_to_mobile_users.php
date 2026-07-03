<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('church_groups')) {
            Schema::create('church_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('functions')->nullable();
                $table->foreignId('leader_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->foreignId('assistant_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('mobile_users') && ! Schema::hasColumn('mobile_users', 'group_id')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->foreignId('group_id')->nullable()->after('gender')->constrained('church_groups')->nullOnDelete();
            });
        }

        foreach ([
            ['name' => 'No group', 'functions' => 'For members who are not currently assigned to a church group.', 'sort_order' => 0],
            ['name' => 'Choir', 'functions' => 'Music ministry, worship support, rehearsals, and special ministrations.', 'sort_order' => 10],
            ['name' => 'Beulah', 'functions' => 'Church service support, care, hospitality, and ministry assistance.', 'sort_order' => 20],
            ['name' => 'Ushering', 'functions' => 'Welcoming worshippers, seating coordination, orderliness, and service flow support.', 'sort_order' => 30],
        ] as $group) {
            DB::table('church_groups')->updateOrInsert(
                ['name' => $group['name']],
                [
                    'functions' => $group['functions'],
                    'sort_order' => $group['sort_order'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mobile_users') && Schema::hasColumn('mobile_users', 'group_id')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('group_id');
            });
        }

        Schema::dropIfExists('church_groups');
    }
};
