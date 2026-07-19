<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalWalletVoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_portal_exposes_wallet_voucher_top_up_flow(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        $this->get('/app/wallet')
            ->assertOk()
            ->assertSee('Top up with voucher')
            ->assertSee('wallet-voucher-topup-form')
            ->assertSee('name="code"', false)
            ->assertSee('minlength="6"', false)
            ->assertSee('/api/goshen-wallet/top-up/voucher')
            ->assertSee("apiPost('/api/goshen-wallet/top-up/voucher'", false)
            ->assertSee("authPayload({ code: data.code })", false)
            ->assertSee('Redeem voucher to wallet');
    }
}
