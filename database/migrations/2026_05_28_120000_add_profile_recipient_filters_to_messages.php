<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['inbox_messages', 'email_notifications'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'selected_country_of_residences')) {
                    $table->json('selected_country_of_residences')->nullable()->after('selected_church_group_ids');
                }

                if (! Schema::hasColumn($tableName, 'selected_genders')) {
                    $table->json('selected_genders')->nullable()->after('selected_country_of_residences');
                }

                if (! Schema::hasColumn($tableName, 'selected_role_ids')) {
                    $table->json('selected_role_ids')->nullable()->after('selected_genders');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['inbox_messages', 'email_notifications'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['selected_country_of_residences', 'selected_genders', 'selected_role_ids'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
