<?php

namespace Tests\Feature;

use App\Filament\Resources\GoshenWalletResource;
use App\Models\AppSetting;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenAdminWalletTopUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_top_up_credits_wallet_and_records_audit_metadata(): void
    {
        $admin = $this->admin();
        $wallet = $this->wallet($this->member(), 10);

        $entry = app(GoshenWalletService::class)->createAdminTopUp($wallet, $admin, [
            'amount' => 45.50,
            'currency' => 'GBP',
            'purpose_type' => 'cash_received',
            'external_reference' => 'CASH-001',
            'note' => 'Cash received by the front desk for member wallet.',
            'request_ip' => '203.0.113.42',
            'request_user_agent' => 'Feature test browser',
        ]);

        $this->assertSame('55.50', $wallet->fresh()->balance);
        $this->assertSame('admin_top_up', $entry->type);
        $this->assertSame('paid', $entry->status);
        $this->assertSame('admin', $entry->gateway);
        $this->assertStringStartsWith('gw_admin_', (string) $entry->provider_reference);
        $this->assertNotNull($entry->settled_at);
        $this->assertSame('admin_panel', $entry->metadata['source']);
        $this->assertSame('cash_received', $entry->metadata['purpose_type']);
        $this->assertSame('CASH-001', $entry->metadata['external_reference']);
        $this->assertSame($admin->email, $entry->metadata['admin_email']);
        $this->assertEquals(10.0, $entry->metadata['wallet_balance_before']);
        $this->assertEquals(55.5, $entry->metadata['wallet_balance_after']);
        $this->assertSame('203.0.113.42', $entry->metadata['request_ip']);
        $this->assertSame('Feature test browser', $entry->metadata['request_user_agent']);
    }

    public function test_admin_top_up_action_obeys_activation_setting(): void
    {
        $admin = $this->admin();
        $wallet = $this->wallet($this->member(), 0);

        $this->actingAs($admin);

        $this->setting('goshen_wallet_enabled', '1');
        $this->setting('goshen_wallet_admin_topup_enabled', '0');
        $this->assertFalse(GoshenWalletResource::canAdminTopUpWallet($wallet));

        $this->setting('goshen_wallet_admin_topup_enabled', '1');
        $this->assertTrue(GoshenWalletResource::canAdminTopUpWallet($wallet));

        GoshenWalletResource::topUpWallet($wallet, [
            'amount' => 20,
            'currency' => 'GBP',
            'purpose_type' => 'bank_transfer_received',
            'external_reference' => 'BANK-001',
            'note' => 'Bank transfer confirmed by finance team.',
            'confirmation' => 'TOP UP WALLET',
        ], app(GoshenWalletService::class));

        $this->assertSame('20.00', $wallet->fresh()->balance);
        $this->assertDatabaseHas('goshen_wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'type' => 'admin_top_up',
            'status' => 'paid',
            'gateway' => 'admin',
            'amount' => 20,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        return $admin;
    }

    private function member(string $email = 'wallet-member@example.test'): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Wallet Member',
            'email' => $email,
            'phone' => '+447700900123',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function wallet(MobileUser $member, float $balance): GoshenWallet
    {
        return GoshenWallet::query()->updateOrCreate(
            ['mobile_user_id' => $member->id],
            [
                'currency' => 'GBP',
                'balance' => $balance,
            ],
        );
    }

    private function setting(string $key, string $value): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => 'features',
                'value' => $value,
                'is_secret' => false,
                'description' => $key,
            ],
        );
    }
}
