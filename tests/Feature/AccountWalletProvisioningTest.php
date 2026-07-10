<?php

namespace Tests\Feature;

use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenWalletService;
use App\Services\LinkedMobileAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountWalletProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_web_admin_receives_one_linked_mobile_identity_triumphant_id_and_wallet(): void
    {
        $admin = User::query()->create([
            'name' => 'Ticket Admin',
            'email' => 'TICKET.ADMIN@example.test',
            'password' => 'StrongPassw0rd!',
        ]);

        $mobile = MobileUser::query()
            ->whereRaw('LOWER(email) = ?', ['ticket.admin@example.test'])
            ->firstOrFail();

        $this->assertSame(1, MobileUser::query()->whereRaw('LOWER(email) = ?', ['ticket.admin@example.test'])->count());
        $this->assertNotNull($mobile->triumphant_id);
        $this->assertTrue(Hash::check('StrongPassw0rd!', $mobile->password));
        $this->assertDatabaseHas('goshen_wallets', ['mobile_user_id' => $mobile->id, 'balance' => 0]);
        $this->assertSame($mobile->id, app(LinkedMobileAccountService::class)->forAdmin($admin)?->id);
    }

    public function test_new_mobile_user_receives_wallet_immediately_and_provisioning_is_idempotent(): void
    {
        $mobile = MobileUser::query()->create([
            'name' => 'Member One',
            'email' => 'member.one@example.test',
            'password' => 'StrongPassw0rd!',
            'is_verified' => true,
            'email_verified_at' => now(),
            'is_blocked' => false,
            'is_deleted' => false,
        ]);

        $this->assertSame(1, GoshenWallet::query()->where('mobile_user_id', $mobile->id)->count());

        app(GoshenWalletService::class)->walletFor($mobile);
        app(GoshenWalletService::class)->walletFor($mobile);

        $this->assertSame(1, GoshenWallet::query()->where('mobile_user_id', $mobile->id)->count());
    }

    public function test_wallet_backfill_provisions_active_users_idempotently_and_excludes_deleted_users(): void
    {
        $activeWithoutWallet = MobileUser::withoutEvents(fn (): MobileUser => MobileUser::query()->create([
            'name' => 'Active Without Wallet',
            'email' => 'active.without.wallet@example.test',
            'password' => 'StrongPassw0rd!',
            'is_deleted' => false,
        ]));

        $activeWithWallet = MobileUser::withoutEvents(fn (): MobileUser => MobileUser::query()->create([
            'name' => 'Active With Wallet',
            'email' => 'active.with.wallet@example.test',
            'password' => 'StrongPassw0rd!',
            'is_deleted' => false,
        ]));
        GoshenWallet::query()->create([
            'mobile_user_id' => $activeWithWallet->id,
            'currency' => 'GBP',
            'balance' => 0,
        ]);

        $deletedWithoutWallet = MobileUser::withoutEvents(fn (): MobileUser => MobileUser::query()->create([
            'name' => 'Deleted Without Wallet',
            'email' => 'deleted.without.wallet@example.test',
            'password' => 'StrongPassw0rd!',
            'is_deleted' => true,
        ]));

        $migration = require database_path('migrations/2026_07_10_160000_backfill_goshen_wallets_for_mobile_users.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, GoshenWallet::query()->where('mobile_user_id', $activeWithoutWallet->id)->count());
        $this->assertSame(1, GoshenWallet::query()->where('mobile_user_id', $activeWithWallet->id)->count());
        $this->assertSame(0, GoshenWallet::query()->where('mobile_user_id', $deletedWithoutWallet->id)->count());
    }
}
