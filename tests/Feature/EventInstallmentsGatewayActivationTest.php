<?php

namespace Tests\Feature;

use App\Services\GoshenVoucherService;
use App\Services\GoshenWalletService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Http\Controllers\Api\PaymentWebhookController;
use Personal\EventInstallments\Services\Gateways\StripeGateway;
use Personal\EventInstallments\Services\PaymentGatewayManager;
use Personal\EventInstallments\Services\PaymentSettlementService;
use Tests\TestCase;

class EventInstallmentsGatewayActivationTest extends TestCase
{
    public function test_release_config_has_a_literal_stripe_only_external_gateway_allowlist(): void
    {
        putenv('EVENT_INSTALLMENTS_ENABLED_EXTERNAL_GATEWAYS=stripe,paystack');

        $applicationConfig = require config_path('event-installments.php');
        $packageConfig = require base_path('packages/event-installments/config/event-installments.php');

        $this->assertSame(['stripe'], $applicationConfig['payments']['enabled_external_gateways']);
        $this->assertSame(['stripe'], $packageConfig['payments']['enabled_external_gateways']);
    }

    public function test_stripe_resolves_and_paystack_is_rejected_for_this_release(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $this->assertInstanceOf(StripeGateway::class, $manager->driver('stripe'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment gateway [paystack] is disabled for this release.');

        $manager->driver('paystack');
    }

    public function test_disabled_default_gateway_cannot_be_used_for_checkout_resolution(): void
    {
        config(['event-installments.payments.default_gateway' => 'paystack']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment gateway [paystack] is disabled for this release.');

        app(PaymentGateway::class);
    }

    public function test_paystack_webhook_is_rejected_before_provider_verification(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment gateway [paystack] is disabled for this release.');

        app(PaymentWebhookController::class)(
            'paystack',
            Request::create('/webhooks/event-installments/paystack', 'POST'),
            app(PaymentGatewayManager::class),
            app(PaymentSettlementService::class),
        );
    }

    public function test_voucher_and_wallet_services_are_outside_the_external_gateway_allowlist(): void
    {
        $this->assertInstanceOf(GoshenVoucherService::class, app(GoshenVoucherService::class));
        $this->assertInstanceOf(GoshenWalletService::class, app(GoshenWalletService::class));
    }
}
