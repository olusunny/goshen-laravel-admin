<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\GoshenReferralCode;
use App\Models\GoshenReferralPointEntry;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Services\GoshenReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Services\CheckInService;
use Tests\TestCase;

class GoshenReferralPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_summary_ensures_missing_existing_user_code(): void
    {
        $member = $this->verifiedMember('existing@example.test', 'Existing Member');
        $token = $member->issueApiToken();
        GoshenReferralCode::query()->where('mobile_user_id', $member->id)->delete();

        $this->assertDatabaseMissing('goshen_referral_codes', [
            'mobile_user_id' => $member->id,
        ]);

        $this->postJson('/api/goshen-retreat/referrals/summary', [
            'data' => ['api_token' => $token],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['data' => ['code', 'points', 'settings']]);

        $this->assertDatabaseHas('goshen_referral_codes', [
            'mobile_user_id' => $member->id,
        ]);
    }

    public function test_paid_registration_with_valid_referral_code_creates_one_pending_award(): void
    {
        $referrer = $this->verifiedMember('referrer@example.test', 'Referral Owner');
        $referee = $this->verifiedMember('referee@example.test', 'Referral Guest');
        $token = $referee->issueApiToken();
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 1000);
        $this->wallet($referee, 1500);

        $code = app(GoshenReferralService::class)->ensureCodeFor($referrer);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($token, $event, $ticketType, [
                'payment_mode' => 'wallet',
                'referral_code' => $code->code,
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value);

        $booking = Booking::query()->where('customer_id', $referee->id)->firstOrFail();
        $this->assertDatabaseHas('goshen_referral_point_entries', [
            'booking_id' => $booking->id,
            'referrer_mobile_user_id' => $referrer->id,
            'referee_mobile_user_id' => $referee->id,
            'status' => GoshenReferralPointEntry::STATUS_PENDING_VALIDATION,
            'points' => 1,
        ]);

        app(GoshenReferralService::class)->createPendingAwardForPaidBooking($booking->fresh(['event', 'attendees']));

        $this->assertSame(1, GoshenReferralPointEntry::query()->where('booking_id', $booking->id)->count());
    }

    public function test_referral_award_validates_when_referred_ticket_checks_in(): void
    {
        [$referrer, , $ticket] = $this->paidReferralFixture();

        app(CheckInService::class)->checkIn(
            ticket: $ticket,
            status: TicketStatus::CheckedIn,
            actorId: null,
            source: 'test_scanner',
        );

        $entry = GoshenReferralPointEntry::query()
            ->where('referrer_mobile_user_id', $referrer->id)
            ->firstOrFail();

        $this->assertSame(GoshenReferralPointEntry::STATUS_VALIDATED, $entry->status);
        $this->assertNotNull($entry->validated_at);
        $this->assertNotNull($entry->notified_at);
        $this->assertDatabaseHas('inbox_messages', [
            'title' => 'Goshen referral points validated',
            'notification_category' => 'events',
        ]);
    }

    public function test_validated_referral_points_convert_to_wallet_fund(): void
    {
        [$referrer, , $ticket] = $this->paidReferralFixture();
        $token = $referrer->issueApiToken();
        $this->wallet($referrer, 25);

        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_referral_wallet_amount_per_point'],
            ['group' => 'goshen_referrals', 'value' => '250', 'is_secret' => false],
        );

        app(CheckInService::class)->checkIn(
            ticket: $ticket,
            status: TicketStatus::CheckedIn,
            actorId: null,
            source: 'test_scanner',
        );

        $this->postJson('/api/goshen-retreat/referrals/convert', [
            'data' => ['api_token' => $token],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('conversion.points_converted', 1)
            ->assertJsonPath('conversion.wallet_amount', 250);

        $this->assertSame('275.00', $referrer->goshenReferralPointEntries()->firstOrFail()->walletLedgerEntry?->wallet?->balance);
        $this->assertDatabaseHas('goshen_wallet_ledger_entries', [
            'type' => 'referral_conversion',
            'status' => 'paid',
            'amount' => 250,
        ]);
        $this->assertDatabaseHas('goshen_referral_point_entries', [
            'referrer_mobile_user_id' => $referrer->id,
            'status' => GoshenReferralPointEntry::STATUS_CONVERTED,
            'converted_points' => 1,
        ]);
    }

    public function test_self_referral_code_is_rejected(): void
    {
        $member = $this->verifiedMember('self@example.test', 'Self Referral');
        $token = $member->issueApiToken();
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 1000);
        $this->wallet($member, 1500);
        $code = app(GoshenReferralService::class)->ensureCodeFor($member);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($token, $event, $ticketType, [
                'payment_mode' => 'wallet',
                'referral_code' => $code->code,
            ]),
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('goshen_referral_point_entries', 0);
    }

    /**
     * @return array{0: MobileUser, 1: MobileUser, 2: Ticket}
     */
    private function paidReferralFixture(): array
    {
        $referrer = $this->verifiedMember('owner@example.test', 'Referral Owner');
        $referee = $this->verifiedMember('guest@example.test', 'Referral Guest');
        $token = $referee->issueApiToken();
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 1000);
        $this->wallet($referee, 1500);
        $code = app(GoshenReferralService::class)->ensureCodeFor($referrer);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($token, $event, $ticketType, [
                'payment_mode' => 'wallet',
                'referral_code' => $code->code,
            ]),
        ])->assertOk();

        $booking = Booking::query()->where('customer_id', $referee->id)->firstOrFail();
        $ticket = Ticket::query()->where('booking_id', $booking->id)->firstOrFail();

        return [$referrer, $referee, $ticket->refresh()];
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
            'attendees' => [[
                'title' => 'Miss',
                'first_name' => 'Referral',
                'last_name' => 'Guest',
                'designation' => 'member',
                'gender' => 'female',
                'marital_status' => 'Single',
                'age_group' => 'adult',
                'free_church_bus_interest' => 'no_thanks',
                'volunteer_department' => 'no_chance_at_the_moment',
            ]],
        ], $overrides);
    }

    private function verifiedMember(
        string $email,
        string $name,
        string $phone = '+2348011112222',
    ): MobileUser {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_referrals_enabled'],
            ['group' => 'goshen_referrals', 'value' => '1', 'is_secret' => false],
        );
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_referral_points_per_paid_registration'],
            ['group' => 'goshen_referrals', 'value' => '1', 'is_secret' => false],
        );

        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret',
            'title' => 'Miss',
            'gender' => 'female',
            'marital_status' => 'Single',
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
            'slug' => 'goshen-retreat-2026-' . Str::random(6),
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
            'sku' => 'ADULT',
            'currency' => 'NGN',
            'price' => $price,
            'min_per_booking' => 1,
            'max_per_booking' => 10,
            'is_active' => true,
        ]);

        return [$event->refresh(), $ticketType->refresh()];
    }

    private function wallet(MobileUser $member, float $balance): GoshenWallet
    {
        return GoshenWallet::query()->updateOrCreate(
            ['mobile_user_id' => $member->id],
            [
                'currency' => 'NGN',
                'balance' => $balance,
            ],
        );
    }
}
