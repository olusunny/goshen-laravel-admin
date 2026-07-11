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
            $table->string('purpose', 32)
                ->default(GoshenVoucher::PURPOSE_PAYMENTS)
                ->index()
                ->after('event_id');
        });

        DB::table('goshen_vouchers')
            ->whereNull('purpose')
            ->update(['purpose' => GoshenVoucher::PURPOSE_PAYMENTS]);
    }

    public function down(): void
    {
        Schema::table('goshen_vouchers', function (Blueprint $table): void {
            $table->dropIndex(['purpose']);
            $table->dropColumn('purpose');
        });
    }
};
