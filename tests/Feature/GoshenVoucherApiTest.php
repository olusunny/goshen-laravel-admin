<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\GoshenVoucher;
use App\Models\GoshenVoucherUsage;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenAdminTicketIssuanceService;
use App\Services\GoshenVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
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

    public function test_pool_voucher_deducts_each_booking_from_shared_balance(): void
    {
        $firstMember = $this->verifiedMember('pool-first@example.test', 'Pool First', '+2348011190001');
        $secondMember = $this->verifiedMember('pool-second@example.test', 'Pool Second', '+2348011190002');
        $thirdMember = $this->verifiedMember('pool-third@example.test', 'Pool Third', '+2348011190003');
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 100);

        $created = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $event->id,
            'label' => 'Shared family and group pool',
            'amount' => 300,
            'currency' => 'NGN',
            'max_uses' => 10,
            'redemption_type' => GoshenVoucher::REDEMPTION_POOL,
        ]);

        foreach ([$firstMember, $secondMember] as $member) {
            $this->postJson('/api/goshen-retreat/bookings', [
                'data' => $this->bookingPayload($member->issueApiToken(), $event, $ticketType, [
                    'payment_mode' => 'voucher',
                    'voucher_code' => $created['code'],
                ]),
            ])->assertOk();
        }

        $voucher = $created['voucher']->fresh();
        $this->assertSame(GoshenVoucher::REDEMPTION_POOL, $voucher->redemption_type);
        $this->assertSame(GoshenVoucher::STATUS_ACTIVE, $voucher->status);
        $this->assertSame(2, $voucher->used_count);
        $this->assertSame('100.00', $voucher->remaining_amount);
        $this->assertSame(2, GoshenVoucherUsage::query()->count());

        $ticketType->forceFill(['price' => 125])->save();

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($thirdMember->issueApiToken(), $event, $ticketType->fresh(), [
                'payment_mode' => 'voucher',
                'voucher_code' => $created['code'],
            ]),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->assertSame('100.00', $voucher->fresh()->remaining_amount);
        $this->assertSame(2, GoshenVoucherUsage::query()->count());
    }

    public function test_pool_voucher_can_be_redeemed_with_unique_display_suffix(): void
    {
        $member = $this->verifiedMember('pool-suffix@example.test', 'Pool Suffix', '+2348011190004');
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 100);

        $created = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $event->id,
            'label' => 'Shared pool redeemable by displayed suffix',
            'amount' => 300,
            'currency' => 'NGN',
            'max_uses' => 10,
            'redemption_type' => GoshenVoucher::REDEMPTION_POOL,
        ]);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($member->issueApiToken(), $event, $ticketType, [
                'payment_mode' => 'voucher',
                'voucher_code' => $created['voucher']->code_suffix,
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value);

        $voucher = $created['voucher']->fresh();
        $this->assertSame(1, $voucher->used_count);
        $this->assertSame('200.00', $voucher->remaining_amount);
        $this->assertSame($created['voucher']->code_suffix, GoshenVoucherUsage::query()->firstOrFail()->code_suffix);
    }

    public function test_generated_voucher_keeps_copyable_full_code_encrypted_for_admin_display(): void
    {
        [$event] = $this->publishedRetreatEvent(price: 100);

        $created = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $event->id,
            'label' => 'Copyable admin code',
            'amount' => 300,
            'currency' => 'NGN',
            'max_uses' => 10,
            'redemption_type' => GoshenVoucher::REDEMPTION_POOL,
        ]);

        $voucher = $created['voucher']->fresh();

        $this->assertSame($created['code'], $voucher->redemption_code);
        $this->assertNotSame($created['code'], $voucher->getRawOriginal('encrypted_code'));
        $this->assertStringEndsWith($voucher->code_suffix, app(GoshenVoucherService::class)->normalizeCode($voucher->redemption_code));
    }

    public function test_display_suffix_must_be_unique_before_it_can_be_used_as_voucher_code(): void
    {
        [$event] = $this->publishedRetreatEvent(price: 100);

        app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $event->id,
            'label' => 'First duplicate suffix',
            'code' => 'GSH-AAAA-BBBB-CCCC-LYXU2S',
            'amount' => 300,
            'currency' => 'NGN',
            'max_uses' => 10,
            'redemption_type' => GoshenVoucher::REDEMPTION_POOL,
        ]);

        app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $event->id,
            'label' => 'Second duplicate suffix',
            'code' => 'GSH-DDDD-EEEE-FFFF-LYXU2S',
            'amount' => 300,
            'currency' => 'NGN',
            'max_uses' => 10,
            'redemption_type' => GoshenVoucher::REDEMPTION_POOL,
        ]);

        $verification = app(GoshenVoucherService::class)->verify('LYXU2S', $event, 100, 'NGN');

        $this->assertFalse($verification['valid']);
        $this->assertSame('This voucher suffix matches more than one voucher. Enter the full voucher code.', $verification['message']);
    }

    public function test_pool_voucher_covers_one_family_ticket_and_multiple_individual_tickets(): void
    {
        $familyMember = $this->verifiedMember('pool-family@example.test', 'Pool Family', '+2348011190010');
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 300);

        $created = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $event->id,
            'label' => 'One family plus ten individuals',
            'amount' => 4200,
            'currency' => 'NGN',
            'max_uses' => 20,
            'redemption_type' => GoshenVoucher::REDEMPTION_POOL,
        ]);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($familyMember->issueApiToken(), $event, $ticketType, [
                'quantity' => 4,
                'payment_mode' => 'voucher',
                'voucher_code' => $created['code'],
                'attendees' => $this->attendeeRows(4),
            ]),
        ])->assertOk();

        $this->assertSame('3000.00', $created['voucher']->fresh()->remaining_amount);

        for ($index = 1; $index <= 10; $index++) {
            $member = $this->verifiedMember(
                "pool-individual-{$index}@example.test",
                "Pool Individual {$index}",
                '+2348011191'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            );

            $this->postJson('/api/goshen-retreat/bookings', [
                'data' => $this->bookingPayload($member->issueApiToken(), $event, $ticketType, [
                    'payment_mode' => 'voucher',
                    'voucher_code' => $created['code'],
                ]),
            ])->assertOk();
        }

        $voucher = $created['voucher']->fresh();
        $this->assertSame(GoshenVoucher::STATUS_EXHAUSTED, $voucher->status);
        $this->assertSame(11, $voucher->used_count);
        $this->assertSame('0.00', $voucher->remaining_amount);
        $this->assertSame(11, GoshenVoucherUsage::query()->count());
        $this->assertSame(14, Ticket::query()->count());
    }

    public function test_admin_reservation_blocks_mobile_registration_without_consuming_voucher(): void
    {
        $member = $this->verifiedMember('admin-reserved@example.test', 'Admin Reserved', '+2348011116677');
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 90);
        $code = $this->voucherCode($event, 90);
        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'currency' => 'NGN',
            'subtotal' => 90,
            'total' => 90,
            'paid_total' => 0,
            'status' => BookingStatus::Pending,
            'metadata' => ['source' => 'filament_admin'],
        ]);
        BookingLine::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'currency' => 'NGN',
            'unit_price' => 90,
            'line_total' => 90,
        ]);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($member->issueApiToken(), $event, $ticketType, [
                'payment_mode' => 'voucher',
                'voucher_code' => $code,
            ]),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->assertSame(1, Booking::query()->where('customer_id', $member->id)->count());
        $this->assertDatabaseCount('goshen_voucher_usages', 0);
        $this->assertSame(GoshenVoucher::STATUS_ACTIVE, GoshenVoucher::query()->firstOrFail()->status);
    }

    public function test_mobile_pending_reservation_blocks_admin_issuance(): void
    {
        $member = $this->verifiedMember('mobile-reserved@example.test', 'Mobile Reserved', '+2348011116688');
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 95);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($member->issueApiToken(), $event, $ticketType, [
                'payment_mode' => 'outright',
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('booking.status', BookingStatus::Pending->value);

        $admin = User::factory()->create();
        $code = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $event->id,
            'currency' => 'NGN',
            'amount' => 95,
            'max_uses' => 1,
        ], adminActor: $admin)['code'];

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Duplicate admin attempt',
                'voucher',
                $code,
            );
            $this->fail('Expected mobile reservation to block admin issuance.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }

        $this->assertSame(1, Booking::query()->where('customer_id', $member->id)->count());
        $this->assertDatabaseCount('goshen_voucher_usages', 0);
        $this->assertDatabaseCount('ei_tickets', 0);
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
                'purpose' => GoshenVoucher::PURPOSE_PAYMENTS,
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

    public function test_event_manager_can_generate_wallet_funding_vouchers_without_event_scope(): void
    {
        $manager = $this->manager('wallet-funding-voucher-manager@example.test');

        $this->postJson('/api/goshen-retreat/vouchers/generate', [
            'data' => [
                'api_token' => $manager->issueApiToken(),
                'purpose' => GoshenVoucher::PURPOSE_WALLET_FUNDING,
                'amount' => 25,
                'currency' => 'GBP',
                'quantity' => 1,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.0.voucher.purpose', GoshenVoucher::PURPOSE_WALLET_FUNDING);

        $voucher = GoshenVoucher::query()->firstOrFail();
        $this->assertNull($voucher->event_id);
        $this->assertSame(GoshenVoucher::PURPOSE_WALLET_FUNDING, $voucher->purpose);
    }

    public function test_event_manager_can_generate_pool_balance_voucher_via_api(): void
    {
        $manager = $this->manager('pool-voucher-manager@example.test');
        [$event] = $this->publishedRetreatEvent(price: 300);

        $this->postJson('/api/goshen-retreat/vouchers/generate', [
            'data' => [
                'api_token' => $manager->issueApiToken(),
                'event_id' => $event->public_id,
                'label' => 'Shared family and group pool',
                'amount' => 4200,
                'currency' => 'GBP',
                'purpose' => GoshenVoucher::PURPOSE_PAYMENTS,
                'redemption_type' => GoshenVoucher::REDEMPTION_POOL,
                'quantity' => 1,
                'max_uses' => 20,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.0.voucher.redemption_type', GoshenVoucher::REDEMPTION_POOL)
            ->assertJsonPath('data.0.voucher.remaining_amount', 4200)
            ->assertJsonPath('data.0.voucher.available_amount', 4200);

        $voucher = GoshenVoucher::query()->firstOrFail();
        $this->assertSame(GoshenVoucher::REDEMPTION_POOL, $voucher->redemption_type);
        $this->assertSame('4200.00', $voucher->remaining_amount);
        $this->assertSame(20, $voucher->max_uses);
    }

    public function test_invalid_voucher_purpose_is_rejected_by_generation_api(): void
    {
        $manager = $this->manager('invalid-purpose-voucher-manager@example.test');

        $this->postJson('/api/goshen-retreat/vouchers/generate', [
            'data' => [
                'api_token' => $manager->issueApiToken(),
                'purpose' => 'wallet',
                'amount' => 25,
                'currency' => 'GBP',
                'quantity' => 1,
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertSame(0, GoshenVoucher::query()->count());
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
                'purpose' => GoshenVoucher::PURPOSE_PAYMENTS,
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
            'slug' => 'goshen-retreat-2026-'.str()->random(6),
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
            'sku' => 'ADULT-'.str()->random(6),
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

    private function attendeeRows(int $count): array
    {
        $rows = [];

        for ($index = 1; $index <= $count; $index++) {
            $rows[] = [
                'title' => 'Mr.',
                'first_name' => "Family{$index}",
                'last_name' => 'Attendee',
                'designation' => 'member',
                'gender' => 'male',
                'marital_status' => 'Single',
                'age_group' => 'child',
                'free_church_bus_interest' => 'no_thanks',
                'volunteer_department' => 'no_chance_at_the_moment',
            ];
        }

        return $rows;
    }
}
