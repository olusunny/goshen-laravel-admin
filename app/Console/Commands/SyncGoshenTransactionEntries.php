<?php

namespace App\Console\Commands;

use App\Models\GoshenTransactionEntry;
use App\Services\GoshenTransactionEntrySyncService;
use Illuminate\Console\Command;

class SyncGoshenTransactionEntries extends Command
{
    protected $signature = 'goshen:sync-transaction-entries {--fresh : Clear the projection before rebuilding it}';

    protected $description = 'Sync Goshen payment, wallet, and voucher records into the central admin transaction index.';

    public function handle(GoshenTransactionEntrySyncService $transactions): int
    {
        if ($this->option('fresh')) {
            GoshenTransactionEntry::query()->delete();
        }

        $counts = $transactions->syncAll();

        foreach ($counts as $source => $count) {
            $this->line(sprintf('%s: %d', $source, $count));
        }

        $this->info(sprintf('Central transaction entries: %d', GoshenTransactionEntry::query()->count()));

        return self::SUCCESS;
    }
}
