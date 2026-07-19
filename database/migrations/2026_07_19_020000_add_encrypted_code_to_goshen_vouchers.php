<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goshen_vouchers', function (Blueprint $table): void {
            if (! Schema::hasColumn('goshen_vouchers', 'encrypted_code')) {
                $table->text('encrypted_code')->nullable()->after('code_suffix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('goshen_vouchers', function (Blueprint $table): void {
            if (Schema::hasColumn('goshen_vouchers', 'encrypted_code')) {
                $table->dropColumn('encrypted_code');
            }
        });
    }
};
