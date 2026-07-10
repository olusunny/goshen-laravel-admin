<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $currency = strtoupper((string) config('event-installments.currency', 'GBP'));

        DB::table('mobile_users')
            ->where('is_deleted', false)
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($users) use ($currency): void {
                $now = now();
                DB::table('goshen_wallets')->insertOrIgnore(
                    $users->map(fn ($user): array => [
                        'mobile_user_id' => $user->id,
                        'currency' => $currency,
                        'balance' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        // Wallets may contain financial history and are intentionally preserved.
    }
};
