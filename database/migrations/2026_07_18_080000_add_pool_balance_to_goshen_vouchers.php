<?php

use App\Models\GoshenVoucher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goshen_vouchers', function (Blueprint $table): void {
            if (! Schema::hasColumn('goshen_vouchers', 'redemption_type')) {
                $table->string('redemption_type')
                    ->default(GoshenVoucher::REDEMPTION_FIXED)
                    ->after('purpose')
                    ->index();
            }

            if (! Schema::hasColumn('goshen_vouchers', 'remaining_amount')) {
                $table->decimal('remaining_amount', 12, 2)
                    ->nullable()
                    ->after('amount')
                    ->index();
            }
        });

        DB::table('goshen_vouchers')
            ->whereNull('redemption_type')
            ->orWhere('redemption_type', '')
            ->update(['redemption_type' => GoshenVoucher::REDEMPTION_FIXED]);
    }

    public function down(): void
    {
        Schema::table('goshen_vouchers', function (Blueprint $table): void {
            if (Schema::hasColumn('goshen_vouchers', 'remaining_amount')) {
                $table->dropColumn('remaining_amount');
            }

            if (Schema::hasColumn('goshen_vouchers', 'redemption_type')) {
                $table->dropColumn('redemption_type');
            }
        });
    }
};
