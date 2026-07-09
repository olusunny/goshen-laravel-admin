<?php

namespace Sunny\Fundraising\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Sunny\Fundraising\Contracts\WalletGatewayContract;
use Sunny\Fundraising\Services\NullWalletGateway;
use Throwable;

class HealthCheckFundraisingCommand extends Command
{
    protected $signature = 'fundraising:health';

    protected $description = 'Check fundraising package installation health.';

    public function handle(): int
    {
        $ok = true;

        try {
            foreach (['fundraising_campaigns', 'fundraising_campaign_media', 'fundraising_campaign_contributions'] as $table) {
                if (! Schema::hasTable($table)) {
                    $this->error("Missing table: {$table}");
                    $ok = false;
                }
            }

            foreach ($this->missingFinancialWriteGuards() as $trigger) {
                $this->error("Missing database write guard trigger: {$trigger}");
                $ok = false;
            }
        } catch (Throwable $exception) {
            $this->error('Database is not available for fundraising health checks: '.$exception->getMessage());
            $ok = false;
        }

        $gateway = app(WalletGatewayContract::class);
        if ($gateway instanceof NullWalletGateway) {
            $this->error('Wallet gateway is not configured.');
            $ok = false;
        }

        if ($ok) {
            $this->info('Fundraising package health check passed.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * @return array<int, string>
     */
    private function missingFinancialWriteGuards(): array
    {
        $expected = [];

        if (Schema::hasTable('donations')) {
            $expected[] = 'donations_prevent_completed_update';
            $expected[] = 'donations_prevent_completed_delete';
        }

        if (Schema::hasTable('fundraising_campaign_contributions')) {
            $expected[] = 'fundraising_contributions_prevent_succeeded_update';
            $expected[] = 'fundraising_contributions_prevent_succeeded_delete';
        }

        if ($expected === []) {
            return [];
        }

        return array_values(array_diff($expected, $this->installedTriggers()));
    }

    /**
     * @return array<int, string>
     */
    private function installedTriggers(): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'trigger'"))
                ->map(fn (object $row): string => (string) ($row->name ?? ''))
                ->filter()
                ->values()
                ->all();
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return collect(DB::select('
                SELECT TRIGGER_NAME AS name
                FROM information_schema.TRIGGERS
                WHERE TRIGGER_SCHEMA = DATABASE()
            '))
                ->map(fn (object $row): string => (string) ($row->name ?? ''))
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }
}
