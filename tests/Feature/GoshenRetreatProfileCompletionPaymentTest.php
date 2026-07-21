<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\GoshenRetreatController;
use App\Models\AppSetting;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Services\GoshenVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Personal\EventInstallments\Contracts\PaymentGateway;
use Personal\EventInstallments\Data\GatewayCheckout;
use Personal\EventInstallments\Data\RefundResult;
use Personal\EventInstallments\Data\VerifiedWebhook;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentInstallment;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenRetreatProfileCompletionPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('event-installments.ticket.email.enabled', false);
    }

    public function test_incomplete_member_cannot_pay_existing_booking_through_any_self_service_payment_entry_point(): void
    {
        [$member, $booking, $installment] = $this->bookingFor($this->incompleteMember());
        $token = $member->issueApiToken();
        $expectedMessage = 'Please complete the member profile before registering for Goshen Retreat: title, marital status, country of residence, state/county/province, address.';

        $this->postJson("/api/goshen-retreat/bookings/{$booking->public_id}/wallet-pay", [
            'data' => ['api_token' => $token],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', $expectedMessage)
            ->assertJsonPath('missing_profile_fields', [
                'title',
                'marital status',
                'country of residence',
                'state/county/province',
                'address',
            ]);

        $this->postJson("/api/goshen-retreat/bookings/{$booking->public_id}/voucher-pay", [
            'data' => [
                'api_token' => $token,
                'voucher_code' => 'UNUSED-TEST-VOUCHER',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', $expectedMessage)
            ->assertJsonPath('missing_profile_fields.0', 'title');

        $gateway = new ProfileCompletionPaymentGateway();
        $response = app(GoshenRetreatController::class)->checkoutPayment(
            Request::create('/', 'POST', ['data' => ['api_token' => $token]]),
            $booking->public_id,
            $installment->public_id,
            $gateway,
        );

        $this->assertSame(422, $response->status());
        $this->assertSame('error', $response->getData(true)['status']);
        $this->assertSame($expectedMessage, $response->getData(true)['message']);
        $this->assertSame(['title', 'marital status', 'country of residence', 'state/county/province', 'address'], $response->getData(true)['missing_profile_fields']);
        $this->assertSame(0, $gateway->checkoutCalls);
    }

    public function test_complete_visitor_can_continue_to_card_checkout_without_member_only_profile_fields(): void
    {
        [$visitor, $booking, $installment] = $this->bookingFor($this->visitor());
        $gateway = new ProfileCompletionPaymentGateway();

        $response = app(GoshenRetreatController::class)->checkoutPayment(
            Request::create('/', 'POST', ['data' => ['api_token' => $visitor->issueApiToken()]]),
            $booking->public_id,
            $installment->public_id,
            $gateway,
        );

        $this->assertSame(200, $response->status());
        $this->assertSame('ok', $response->getData(true)['status']);
        $this->assertSame(1, $gateway->checkoutCalls);
    }

    public function test_visitor_can_pay_existing_bookings_with_wallet_voucher_and_card_checkout(): void
    {
        $visitor = $this->visitor();
        $token = $visitor->issueApiToken();

        [, $walletBooking] = $this->bookingFor($visitor);
        GoshenWallet::query()->updateOrCreate(['mobile_user_id' => $visitor->id], [
            'currency' => 'NGN',
            'balance' => 250,
        ]);

        $this->postJson("/api/goshen-retreat/bookings/{$walletBooking->public_id}/wallet-pay", [
            'data' => ['api_token' => $token],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value);

        [, $voucherBooking] = $this->bookingFor($visitor);
        $voucher = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $voucherBooking->event_id,
            'amount' => 250,
            'currency' => 'NGN',
            'max_uses' => 1,
        ]);

        $this->postJson("/api/goshen-retreat/bookings/{$voucherBooking->public_id}/voucher-pay", [
            'data' => [
                'api_token' => $token,
                'voucher_code' => $voucher['code'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value);

        [, $cardBooking, $cardInstallment] = $this->bookingFor($visitor);
        $gateway = new ProfileCompletionPaymentGateway();
        $response = app(GoshenRetreatController::class)->checkoutPayment(
            Request::create('/', 'POST', ['data' => ['api_token' => $token]]),
            $cardBooking->public_id,
            $cardInstallment->public_id,
            $gateway,
        );

        $this->assertSame(200, $response->status());
        $this->assertSame('ok', $response->getData(true)['status']);
        $this->assertSame(1, $gateway->checkoutCalls);
    }

    public function test_authorized_administrator_can_pay_an_incomplete_member_booking_with_a_voucher(): void
    {
        $beneficiary = $this->incompleteMember();
        [, $booking] = $this->bookingFor($beneficiary);
        $manager = $this->visitor();
        Role::findOrCreate('event_manager', 'mobile');
        $manager->assignRole('event_manager');
        $voucher = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $booking->event_id,
            'amount' => 250,
            'currency' => 'NGN',
            'max_uses' => 1,
        ]);

        $this->postJson("/api/goshen-retreat/bookings/{$booking->public_id}/voucher-pay", [
            'data' => [
                'api_token' => $manager->issueApiToken(),
                'voucher_code' => $voucher['code'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value);
    }

    /** @return array{0: MobileUser, 1: Booking, 2: PaymentInstallment} */
    private function bookingFor(MobileUser $member): array
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        $event = Event::query()->create([
            'name' => 'Goshen Retreat Profile Guard',
            'slug' => 'goshen-profile-guard-'.str()->random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);
        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Profile Guard Ticket',
            'sku' => 'PROFILE-GUARD-'.str()->random(8),
            'currency' => 'NGN',
            'price' => 250,
            'min_per_booking' => 1,
            'max_per_booking' => 1,
            'is_active' => true,
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
        BookingLine::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'currency' => 'NGN',
            'unit_price' => 250,
            'line_total' => 250,
        ]);
        Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Profile',
            'last_name' => 'Guard',
            'email' => $member->email,
            'phone' => $member->phone,
        ]);
        $installment = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'NGN',
            'amount' => 250,
            'paid_amount' => 0,
            'due_on' => now()->toDateString(),
            'status' => InstallmentStatus::Pending,
        ]);

        return [$member, $booking, $installment];
    }

    private function incompleteMember(): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Incomplete Payment Member',
            'email' => 'incomplete-'.str()->random(8).'@example.test',
            'phone' => '+23480'.random_int(10000000, 99999999),
            'password' => 'secret',
            'gender' => 'female',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function visitor(): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Visitor Payment Member',
            'email' => 'visitor-'.str()->random(8).'@example.test',
            'phone' => '+23480'.random_int(10000000, 99999999),
            'password' => 'secret',
            'gender' => 'female',
            'member_type' => 'visitor',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }
}

class ProfileCompletionPaymentGateway implements PaymentGateway
{
    public int $checkoutCalls = 0;

    public function createCheckout(PaymentInstallment $installment): GatewayCheckout
    {
        $this->checkoutCalls++;

        return new GatewayCheckout(
            'stripe',
            'profile_completion_checkout_'.$this->checkoutCalls,
            'https://stripe.test/profile-completion',
            ['url' => 'https://stripe.test/profile-completion'],
        );
    }

    public function verifyWebhook(Request $request): VerifiedWebhook
    {
        throw new RuntimeException('Not used.');
    }

    public function refund($transaction, float $amount, string $idempotencyKey): RefundResult
    {
        throw new RuntimeException('Not used.');
    }
}
