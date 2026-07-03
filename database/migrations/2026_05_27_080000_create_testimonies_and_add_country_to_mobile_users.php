<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mobile_users') && ! Schema::hasColumn('mobile_users', 'country_of_residence')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->string('country_of_residence', 120)->nullable()->after('group_id');
            });
        }

        Schema::create('testimonies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('audio_path')->nullable();
            $table->unsignedSmallInteger('audio_duration_seconds')->nullable();
            $table->boolean('is_anonymous')->default(false)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->index();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'approved_at']);
            $table->index(['mobile_user_id', 'created_at']);
        });

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'testimonies_enabled'],
            [
                'group' => 'modules',
                'value' => '1',
                'is_secret' => false,
                'description' => 'Enable or disable the Testimonies & Thanksgiving Wall in the mobile app.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonies');

        if (Schema::hasTable('mobile_users') && Schema::hasColumn('mobile_users', 'country_of_residence')) {
            Schema::table('mobile_users', function (Blueprint $table) {
                $table->dropColumn('country_of_residence');
            });
        }

        DB::table('app_settings')->where('key', 'testimonies_enabled')->delete();
    }
};
