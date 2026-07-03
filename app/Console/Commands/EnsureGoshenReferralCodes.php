<?php

namespace App\Console\Commands;

use App\Services\GoshenReferralService;
use Illuminate\Console\Command;

class EnsureGoshenReferralCodes extends Command
{
    protected $signature = 'goshen:ensure-referral-codes {--chunk=200 : Number of users to scan per chunk}';

    protected $description = 'Idempotently backfill missing Goshen Retreat referral codes for existing mobile users.';

    public function handle(GoshenReferralService $referrals): int
    {
        $created = $referrals->ensureCodesForExistingUsers(max(1, (int) $this->option('chunk')));

        $this->info("Created {$created} missing Goshen referral code(s).");

        return self::SUCCESS;
    }
}
