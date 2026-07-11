<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetGoshenDemoWalletVoucherData extends Command
{
    private const CONFIRMATION_TOKEN = 'RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA';

    protected $signature = 'goshen:reset-demo-wallet-voucher-data
        {--confirm= : Type RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA to perform the reset}
        {--dry-run : Print affected record counts without changing data}';

    protected $description = 'Reset demo Goshen wallet balances, wallet activity, and vouchers while preserving members and payment history.';

    public function handle(): int
    {
        if ($this->option('confirm') !== self::CONFIRMATION_TOKEN) {
            $this->error('Confirmation token required; no data was changed.');

            return self::FAILURE;
        }

        $this->line('Before reset:');
        $this->writeCounts($this->counts());

        if ((bool) $this->option('dry-run')) {
            $this->info('Dry run only; no data was changed.');

            return self::SUCCESS;
        }

        DB::transaction(function (): void {
            DB::table('goshen_voucher_usages')->delete();
            DB::table('goshen_vouchers')->delete();
            DB::table('goshen_wallet_withdrawal_requests')->delete();
            DB::table('goshen_wallet_savings_plans')->delete();
            DB::table('goshen_wallet_goals')->delete();
            DB::table('goshen_wallet_ledger_entries')->delete();
            DB::table('goshen_wallets')->update([
                'balance' => 0,
                'goal_amount' => null,
                'goal_label' => null,
                'goal_target_at' => null,
                'updated_at' => now(),
            ]);
        });

        $this->info('Reset complete.');
        $this->line('After reset:');
        $this->writeCounts($this->counts());

        return self::SUCCESS;
    }

    /**
     * @return array<string, int|string>
     */
    private function counts(): array
    {
        return [
            'goshen_voucher_usages' => DB::table('goshen_voucher_usages')->count(),
            'goshen_vouchers' => DB::table('goshen_vouchers')->count(),
            'goshen_wallet_withdrawal_requests' => DB::table('goshen_wallet_withdrawal_requests')->count(),
            'goshen_wallet_savings_plans' => DB::table('goshen_wallet_savings_plans')->count(),
            'goshen_wallet_goals' => DB::table('goshen_wallet_goals')->count(),
            'goshen_wallet_ledger_entries' => DB::table('goshen_wallet_ledger_entries')->count(),
            'goshen_wallets' => DB::table('goshen_wallets')->count(),
            'goshen_wallet_balance_total' => number_format((float) DB::table('goshen_wallets')->sum('balance'), 2, '.', ''),
        ];
    }

    /**
     * @param  array<string, int|string>  $counts
     */
    private function writeCounts(array $counts): void
    {
        $this->table(
            ['Metric', 'Count'],
            collect($counts)
                ->map(fn (int|string $count, string $metric): array => [$metric, $count])
                ->values()
                ->all(),
        );
    }
}
