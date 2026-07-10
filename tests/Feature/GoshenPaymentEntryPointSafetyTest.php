<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\GoshenRetreatController;
use App\Filament\Resources\GoshenBookingResource\Pages\ViewGoshenBooking;
use App\Models\AppSetting;
use App\Models\GoshenVoucherUsage;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\GatewayCheckout;
use Personal\EventInstallments\Data\RefundResult;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenPaymentEntryPointSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_paystack_checkout_is_reused_and_never_creates_a_second_payment_transaction(): void
    {
        [$member, $booking, $record, $token] = $this->memberBooking();
        $existing = $this->externalCheckout($booking, $record, 'paystack', [
            'data' => ['authorization_url' => 'https://paystack.test/authorize'],
        ]);
        $gateway = new FakeEntryPointGateway();
        $controller = app(GoshenRetreatController::class);
        $request = Request::create('/', 'POST', ['data' => ['api_token' => $token]]);

        $first = $controller->checkoutPayment($request, $booking->public_id, $record->public_id, $gateway);
        $second = $controller->checkoutPayment($request, $booking->public_id, $record->public_id, $gateway);

        $this->assertSame('https://paystack.test/authorize', $first->getData(true)['checkout']['checkout_url']);
        $this->assertSame($existing->provider_reference, $second->getData(true)['checkout']['reference']);
        $this->assertSame(0, $gateway->checkoutCalls);
        $this->assertSame(1, PaymentTransaction::query()->where('booking_id', $booking->id)->count());
    }

    public function test_expired_stripe_checkout_is_closed_before_one_new_checkout_is_created(): void
    {
        [, $booking, $record, $token] = $this->memberBooking();
        $expired = $this->externalCheckout($booking, $record, 'stripe', [
            'url' => 'https://stripe.test/expired',
            'expires_at' => now()->subMinute()->timestamp,
        ]);
        $gateway = new FakeEntryPointGateway();

        $response = app(GoshenRetreatController::class)->checkoutPayment(
            Request::create('/', 'POST', ['data' => ['api_token' => $token]]),
            $booking->public_id,
            $record->public_id,
            $gateway,
        );

        $this->assertSame('ok', $response->getData(true)['status']);
        $this->assertSame(1, $gateway->checkoutCalls);
        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame(2, PaymentTransaction::query()->where('booking_id', $booking->id)->count());
    }

    public function test_voucher_payment_rejects_while_external_checkout_is_live(): void
    {
        [$member, $booking, $record] = $this->memberBooking();
        $this->externalCheckout($booking, $record, 'stripe', ['url' => 'https://stripe.test/live']);
        $created = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $booking->event_id,
            'amount' => 250,
            'currency' => 'NGN',
            'max_uses' => 1,
        ]);

        try {
            app(GoshenVoucherService::class)->redeemForBooking($booking, $record, $created['code'], $member);
            $this->fail('Expected voucher settlement to reject an active external checkout.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('card checkout is already active', $exception->getMessage());
        }

        $this->assertSame(0, GoshenVoucherUsage::query()->count());
        $this->assertSame(0, $created['voucher']->fresh()->used_count);
    }

    public function test_wallet_payment_rejects_while_external_checkout_is_live_without_debit(): void
    {
        [$member, $booking, $record, $token] = $this->memberBooking();
        $wallet = $member->wallet()->firstOrFail();
        $wallet->forceFill(['currency' => 'NGN', 'balance' => 500])->save();
        $this->externalCheckout($booking, $record, 'paystack', [
            'data' => ['authorization_url' => 'https://paystack.test/live'],
        ]);

        $this->postJson("/api/goshen-retreat/bookings/{$booking->public_id}/wallet-pay", [
            'data' => ['api_token' => $token],
        ])->assertUnprocessable()->assertJsonPath('status', 'error');

        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertSame(0, $wallet->ledgerEntries()->where('type', 'retreat_payment')->count());
    }

    public function test_offline_guard_rejects_while_external_checkout_is_live(): void
    {
        [, $booking, $record] = $this->memberBooking();
        $this->externalCheckout($booking, $record, 'stripe', ['url' => 'https://stripe.test/live']);
        $permission = Permission::findOrCreate('manage_goshen_booking', 'web');
        $role = Role::findOrCreate('payment_desk', 'web');
        $role->givePermissionTo($permission);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        try {
            Livewire::actingAs($admin)
                ->test(ViewGoshenBooking::class, ['record' => $booking->getRouteKey()])
                ->callAction('markOfflinePayment', data: [
                    'installment_id' => $record->id,
                    'reference' => 'CASH-TEST',
                    'note' => 'Must be blocked while card checkout is live.',
                ]);
            $this->fail('Expected offline settlement to reject an active card checkout.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('card checkout is already active', $exception->getMessage());
        }

        $this->assertDatabaseMissing('ei_payment_transactions', [
            'booking_id' => $booking->id,
            'gateway' => 'offline',
        ]);
    }

    /** @return array{0: MobileUser, 1: Booking, 2: PaymentInstallment, 3: string} */
    private function memberBooking(): array
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );
        $member = MobileUser::query()->create([
            'name' => 'Payment Safety Member',
            'email' => str()->random(8) . '@example.test',
            'phone' => '+23480' . random_int(10000000, 99999999),
            'password' => 'secret',
            'title' => 'Mr.',
            'gender' => 'male',
            'marital_status' => 'Married',
            'member_type' => 'church_member',
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Lagos',
            'address' => '1 Test Road',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $event = Event::query()->create([
            'name' => 'Goshen Retreat Safety',
            'slug' => 'goshen-safety-' . str()->random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);
        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'NGN',
            'subtotal' => 250,
            'total' => 250,
            'paid_total' => 0,
            'status' => BookingStatus::Pending,
        ]);
        $record = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'NGN',
            'amount' => 250,
            'paid_amount' => 0,
            'due_on' => now()->toDateString(),
            'status' => InstallmentStatus::Pending,
        ]);

        return [$member, $booking, $record, $member->issueApiToken()];
    }

    private function externalCheckout(Booking $booking, PaymentInstallment $record, string $gateway, array $payload): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'installment_id' => $record->id,
            'gateway' => $gateway,
            'provider_reference' => 'external_' . str()->random(12),
            'currency' => 'NGN',
            'amount' => 250,
            'status' => 'pending',
            'payload' => $payload,
        ]);
    }
}

class FakeEntryPointGateway implements PaymentGateway
{
    public int $checkoutCalls = 0;

    public function createCheckout(PaymentInstallment $installment): GatewayCheckout
    {
        $this->checkoutCalls++;

        return new GatewayCheckout(
            'stripe',
            'new_checkout_' . $this->checkoutCalls,
            'https://stripe.test/new',
            ['url' => 'https://stripe.test/new', 'expires_at' => now()->addHour()->timestamp],
        );
    }

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        throw new RuntimeException('Not used.');
    }

    public function refund(PaymentTransaction $transaction, float $amount): RefundResult
    {
        throw new RuntimeException('Not used.');
    }
}
