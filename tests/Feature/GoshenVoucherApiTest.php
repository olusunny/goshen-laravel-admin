<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\GoshenVoucher;
use App\Models\GoshenVoucherUsage;
use App\Models\MobileUser;
use App\Services\GoshenVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Models\Ticket;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenVoucherApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_register_with_voucher_and_ticket_is_issued(): void
    {
        $member = $this->verifiedMember();
        $token = $member->issueApiToken();
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 125);
        $code = $this->voucherCode($event, 125);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($token, $event, $ticketType, [
                'payment_mode' => 'voucher',
                'voucher_code' => $code,
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value)
            ->assertJsonPath('booking.payment_mode', 'voucher')
            ->assertJsonCount(1, 'booking.tickets');

        $booking = Booking::query()->where('customer_id', $member->id)->firstOrFail();

        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertSame('voucher', $booking->metadata['payment_mode'] ?? null);
        $this->assertSame(1, Ticket::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(1, GoshenVoucherUsage::query()->where('booking_id', $booking->id)->count());
        $this->assertDatabaseHas('ei_payment_transactions', [
            'booking_id' => $booking->id,
            'gateway' => 'voucher',
            'status' => 'paid',
        ]);
        $this->assertSame(GoshenVoucher::STATUS_EXHAUSTED, GoshenVoucher::query()->firstOrFail()->status);
    }

    public function test_voucher_cannot_be_reused_for_another_registration(): void
    {
        $firstMember = $this->verifiedMember('first-voucher@example.test', 'First Voucher', '+2348011112233');
        $secondMember = $this->verifiedMember('second-voucher@example.test', 'Second Voucher', '+2348011112244');
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 80);
        $code = $this->voucherCode($event, 80);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($firstMember->issueApiToken(), $event, $ticketType, [
                'payment_mode' => 'voucher',
                'voucher_code' => $code,
            ]),
        ])->assertOk();

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($secondMember->issueApiToken(), $event, $ticketType, [
                'payment_mode' => 'voucher',
                'voucher_code' => $code,
            ]),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->assertSame(1, GoshenVoucherUsage::query()->count());
        $this->assertSame(0, Booking::query()->where('customer_id', $secondMember->id)->count());
    }

    public function test_event_manager_can_generate_vouchers_and_view_usage(): void
    {
        $manager = $this->manager();
        $member = $this->verifiedMember('voucher-usage-member@example.test', 'Voucher Usage Member', '+2348011112255');
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 40);

        $generateResponse = $this->postJson('/api/goshen-retreat/vouchers/generate', [
            'data' => [
                'api_token' => $manager->issueApiToken(),
                'event_id' => $event->public_id,
                'label' => 'Offline cash batch',
                'amount' => 40,
                'currency' => 'NGN',
                'quantity' => 2,
                'max_uses' => 1,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonCount(2, 'data');

        $code = $generateResponse->json('data.0.code');

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($member->issueApiToken(), $event, $ticketType, [
                'payment_mode' => 'voucher',
                'voucher_code' => $code,
            ]),
        ])->assertOk();

        $this->postJson('/api/goshen-retreat/vouchers/usages', [
            'data' => [
                'api_token' => $manager->fresh()->issueApiToken(),
                'event_id' => $event->public_id,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.member.email', $member->email)
            ->assertJsonPath('data.0.source', 'mobile_registration');
    }

    public function test_regular_member_cannot_generate_vouchers(): void
    {
        $member = $this->verifiedMember('not-manager@example.test', 'Not Manager', '+2348011112266');
        [$event] = $this->publishedRetreatEvent(price: 40);

        $this->postJson('/api/goshen-retreat/vouchers/generate', [
            'data' => [
                'api_token' => $member->issueApiToken(),
                'event_id' => $event->public_id,
                'amount' => 40,
                'currency' => 'NGN',
                'quantity' => 1,
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');
    }

    public function test_event_manager_can_pay_existing_member_booking_with_voucher(): void
    {
        $manager = $this->manager('voucher-manager-pay@example.test');
        $member = $this->verifiedMember('voucher-beneficiary@example.test', 'Voucher Beneficiary', '+2348011112277');
        [$booking] = $this->bookingWithInstallment($member);
        $code = $this->voucherCode($booking->event, 1000);

        $this->postJson("/api/goshen-retreat/bookings/{$booking->public_id}/voucher-pay", [
            'data' => [
                'api_token' => $manager->issueApiToken(),
                'voucher_code' => $code,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value)
            ->assertJsonPath('booking.payment_mode', 'voucher');

        $usage = GoshenVoucherUsage::query()->firstOrFail();

        $this->assertSame('control_hub', $usage->source);
        $this->assertSame($member->id, $usage->mobile_user_id);
        $this->assertSame($manager->id, $usage->redeemed_by_mobile_user_id);
        $this->assertSame(BookingStatus::Paid, $booking->fresh()->status);
    }

    public function test_event_manager_can_create_member_and_register_with_voucher(): void
    {
        $manager = $this->manager('voucher-manager-register@example.test');
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 95);
        $code = $this->voucherCode($event, 95);

        $memberResponse = $this->postJson('/api/goshen-retreat/members', [
            'data' => [
                'api_token' => $manager->issueApiToken(),
                'first_name' => 'Control',
                'last_name' => 'Member',
                'email' => 'control-member@example.test',
                'phone' => '+2348099990000',
                'title' => 'Miss',
                'gender' => 'female',
                'marital_status' => 'Single',
                'member_type' => 'visitor',
                'country_of_residence' => 'United Kingdom',
                'state_county_province' => 'London',
                'address' => '1 Retreat Street',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.member.profile_needs_update', false);

        $memberId = $memberResponse->json('data.member.id');

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($manager->fresh()->issueApiToken(), $event, $ticketType, [
                'managed_member_id' => $memberId,
                'payment_mode' => 'voucher',
                'voucher_code' => $code,
                'attendees' => [[
                    'title' => 'Miss',
                    'first_name' => 'Control',
                    'last_name' => 'Member',
                    'email' => 'control-member@example.test',
                    'phone' => '+2348099990000',
                    'designation' => 'member',
                    'gender' => 'female',
                    'marital_status' => 'Single',
                    'age_group' => 'adult',
                    'free_church_bus_interest' => 'no_thanks',
                    'volunteer_department' => 'no_chance_at_the_moment',
                ]],
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value)
            ->assertJsonPath('booking.payment_mode', 'voucher');

        $member = MobileUser::query()->findOrFail($memberId);
        $booking = Booking::query()->where('customer_id', $member->id)->firstOrFail();
        $usage = GoshenVoucherUsage::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame('control_hub_registration', $usage->source);
        $this->assertSame($member->id, $usage->mobile_user_id);
        $this->assertSame($manager->id, $usage->redeemed_by_mobile_user_id);
        $this->assertSame(true, $booking->metadata['manager_assisted'] ?? null);
    }

    private function voucherCode(Event $event, float $amount): string
    {
        $created = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $event->id,
            'label' => 'Offline cash',
            'amount' => $amount,
            'currency' => 'NGN',
            'max_uses' => 1,
        ]);

        return $created['code'];
    }

    private function manager(string $email = 'voucher-manager@example.test'): MobileUser
    {
        $manager = $this->verifiedMember($email, 'Voucher Manager', '+2348011119999');
        Role::findOrCreate('event_manager', 'mobile');
        $manager->assignRole('event_manager');

        return $manager;
    }

    private function verifiedMember(
        string $email = 'voucher-member@example.test',
        string $name = 'Voucher Member',
        string $phone = '+2348011112222',
    ): MobileUser {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret',
            'title' => 'Mr.',
            'gender' => 'male',
            'marital_status' => 'Married',
            'member_type' => 'church_member',
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Lagos',
            'address' => '1 Mercy Road, Lagos',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return array{0: Event, 1: EventTicketType}
     */
    private function publishedRetreatEvent(float $price = 1000): array
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026-' . str()->random(6),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => ['module' => 'goshen_retreat'],
        ]);

        EventSchedule::query()->create([
            'event_id' => $event->id,
            'day_number' => 1,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'metadata' => ['title' => 'Opening service'],
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'sku' => 'ADULT-' . str()->random(6),
            'currency' => 'NGN',
            'price' => $price,
            'min_per_booking' => 1,
            'max_per_booking' => 10,
            'is_active' => true,
        ]);

        return [$event->refresh(), $ticketType->refresh()];
    }

    /**
     * @return array{0: Booking, 1: PaymentInstallment}
     */
    private function bookingWithInstallment(MobileUser $member): array
    {
        [$event, $ticketType] = $this->publishedRetreatEvent();

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'NGN',
            'subtotal' => 1000,
            'total' => 1000,
            'paid_total' => 0,
            'status' => BookingStatus::Pending,
        ]);

        Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Voucher',
            'last_name' => 'Beneficiary',
            'email' => $member->email,
            'phone' => $member->phone,
            'custom_fields' => [
                'title' => 'Mrs.',
                'gender' => 'female',
                'marital_status' => 'Married',
                'age_group' => 'adult',
                'free_church_bus_interest' => 'no_thanks',
                'volunteer_department' => 'media',
            ],
        ]);

        $installment = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'NGN',
            'amount' => 1000,
            'paid_amount' => 0,
            'due_on' => now()->addWeek()->toDateString(),
            'status' => InstallmentStatus::Pending,
        ]);

        PaymentTransaction::query()->where('booking_id', $booking->id)->delete();

        return [$booking->refresh(), $installment->refresh()];
    }

    private function bookingPayload(string $token, Event $event, EventTicketType $ticketType, array $overrides = []): array
    {
        return array_merge([
            'api_token' => $token,
            'event_id' => $event->public_id,
            'ticket_type_id' => $ticketType->public_id,
            'quantity' => 1,
            'uk_privacy_consent' => true,
            'privacy_policy_version' => 'uk-gdpr-2026-06',
            'attendees' => [
                [
                    'title' => 'Mr.',
                    'first_name' => 'Voucher',
                    'last_name' => 'Member',
                    'designation' => 'member',
                    'gender' => 'male',
                    'marital_status' => 'Married',
                    'age_group' => 'adult',
                    'free_church_bus_interest' => 'yes',
                    'volunteer_department' => 'media',
                ],
            ],
        ], $overrides);
    }
}
