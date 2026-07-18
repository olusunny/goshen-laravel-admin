<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Services\GoshenWalletService;
use App\Services\StripePaymentSettings;
use App\Services\WalletSecurityResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoshenWalletStripeReturnUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_wallet_top_up_checkout_uses_app_return_urls_when_requested(): void
    {
        config(['services.stripe.secret' => 'sk_test_wallet']);
        $this->setting('goshen_wallet_enabled', '1');

        $member = MobileUser::query()->create([
            'name' => 'Wallet Return Member',
            'email' => 'wallet-return@example.test',
            'phone' => '+447700900789',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $wallet = GoshenWallet::query()->firstOrCreate(
            ['mobile_user_id' => $member->id],
            [
                'currency' => 'GBP',
                'balance' => 0,
            ],
        );

        $service = new FakeWalletReturnUrlService(
            app(StripePaymentSettings::class),
            app(WalletSecurityResetService::class),
        );

        $checkout = $service->createTopUpCheckout($wallet, [
            'amount' => 25,
            'currency' => 'GBP',
            'return_to_app' => true,
        ]);

        $this->assertSame('https://stripe.test/checkout/cs_test_wallet', $checkout['checkout']['checkout_url']);
        $this->assertSame(
            'triumphant://goshen-wallet/success?session_id={CHECKOUT_SESSION_ID}',
            $service->checkoutSessions[0]['payload']['success_url'],
        );
        $this->assertSame(
            'triumphant://goshen-wallet/cancelled',
            $service->checkoutSessions[0]['payload']['cancel_url'],
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

class FakeWalletReturnUrlService extends GoshenWalletService
{
    public array $checkoutSessions = [];

    protected function createCheckoutSession(array $payload, array $options): object
    {
        $this->checkoutSessions[] = [
            'payload' => $payload,
            'options' => $options,
        ];

        return new class
        {
            public function toArray(): array
            {
                return [
                    'id' => 'cs_test_wallet',
                    'url' => 'https://stripe.test/checkout/cs_test_wallet',
                ];
            }
        };
    }
}
