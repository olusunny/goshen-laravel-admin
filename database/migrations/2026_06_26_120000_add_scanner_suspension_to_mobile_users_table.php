<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('mobile_users', 'scanner_suspended_at')) {
                $table->timestamp('scanner_suspended_at')->nullable()->after('last_seen_at');
            }

            if (! Schema::hasColumn('mobile_users', 'scanner_suspension_reason')) {
                $table->string('scanner_suspension_reason')->nullable()->after('scanner_suspended_at');
            }

            if (! Schema::hasColumn('mobile_users', 'scanner_suspended_by')) {
                $table->unsignedBigInteger('scanner_suspended_by')->nullable()->after('scanner_suspension_reason')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('mobile_users', function (Blueprint $table): void {
            foreach (['scanner_suspended_by', 'scanner_suspension_reason', 'scanner_suspended_at'] as $column) {
                if (Schema::hasColumn('mobile_users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
