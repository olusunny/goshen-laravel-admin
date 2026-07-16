<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_HOST = 'https://goshen.shotfaz.com';

    private const CURRENT_HOST = 'https://portal.goshenretreat.uk';

    public function up(): void
    {
        foreach ($this->returnUrlKeys() as $key) {
            $setting = DB::table('app_settings')->where('key', $key)->first();
            $current = trim((string) ($setting->value ?? ''));

            if ($current === '' || ! str_starts_with($current, self::LEGACY_HOST)) {
                continue;
            }

            DB::table('app_settings')
                ->where('id', $setting->id)
                ->update([
                    'value' => self::CURRENT_HOST.substr($current, strlen(self::LEGACY_HOST)),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Do not restore the retired host on rollback.
    }

    private function returnUrlKeys(): array
    {
        return [
            'stripe_giving_success_url',
            'stripe_giving_cancel_url',
            'stripe_event_success_url',
            'stripe_event_cancel_url',
            'stripe_wallet_success_url',
            'stripe_wallet_cancel_url',
        ];
    }
};
