<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goshen_experience_surveys', function (Blueprint $table): void {
            $table->boolean('allow_all_authenticated_users')
                ->default(false)
                ->after('is_active')
                ->index();
        });

        Schema::table('goshen_experience_questions', function (Blueprint $table): void {
            $table->json('conditional_logic')->nullable()->after('options');
            $table->json('settings')->nullable()->after('conditional_logic');
        });
    }

    public function down(): void
    {
        Schema::table('goshen_experience_questions', function (Blueprint $table): void {
            $table->dropColumn(['conditional_logic', 'settings']);
        });

        Schema::table('goshen_experience_surveys', function (Blueprint $table): void {
            $table->dropColumn('allow_all_authenticated_users');
        });
    }
};
