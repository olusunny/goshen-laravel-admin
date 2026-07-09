<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\MobileUser;
use App\Services\GoshenRegistrationFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAttendeeField;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Tests\TestCase;

class GoshenRegistrationFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_payload_seeds_default_fields_when_event_has_no_fields(): void
    {
        $this->enableGoshenRetreat();
        [$event] = $this->publishedRetreatEvent();

        $this->assertSame(0, EventAttendeeField::query()->where('event_id', $event->id)->count());

        $response = $this->getJson('/api/goshen-retreat/events')
            ->assertOk();

        $fields = collect($response->json('data.0.attendee_fields'))->keyBy('key');

        $this->assertTrue($fields->has('designation'));
        $this->assertSame('select', $fields->get('designation')['type'] ?? null);
        $this->assertTrue($fields->get('designation')['is_required'] ?? false);
        $this->assertGreaterThan(0, EventAttendeeField::query()->where('event_id', $event->id)->count());
    }

    public function test_event_payload_includes_default_designation_and_admin_managed_attendee_fields(): void
    {
        $this->enableGoshenRetreat();
        [$event] = $this->publishedRetreatEvent();
        app(GoshenRegistrationFieldService::class)->ensureDefaultsForEvent($event);

        EventAttendeeField::query()->create([
            'event_id' => $event->id,
            'key' => 'arrival_window',
            'label' => 'Arrival window',
            'type' => 'single_select',
            'is_required' => true,
            'is_unique' => false,
            'options' => [
                ['label' => 'Morning', 'value' => 'morning'],
                ['label' => 'Evening', 'value' => 'evening'],
            ],
            'sort_order' => 15,
        ]);

        $response = $this->getJson('/api/goshen-retreat/events')
            ->assertOk();

        $fields = collect($response->json('data.0.attendee_fields'))->keyBy('key');
        $formFields = collect($response->json('data.0.registration_form.attendee_fields'))->keyBy('key');

        $this->assertTrue($fields->has('designation'));
        $this->assertSame('select', $fields->get('designation')['type'] ?? null);
        $this->assertTrue($fields->get('designation')['is_required'] ?? false);
        $this->assertSame(
            ['', 'member', 'worker', 'minister', 'pastor', 'guest', 'other'],
            collect($fields->get('designation')['options'] ?? [])->pluck('value')->all(),
        );

        $this->assertTrue($fields->has('arrival_window'));
        $this->assertTrue($formFields->has('arrival_window'));
        $this->assertSame('select', $fields->get('arrival_window')['type'] ?? null);
        $this->assertSame(['morning', 'evening'], collect($fields->get('arrival_window')['options'] ?? [])->pluck('value')->all());
    }

    public function test_event_payload_respects_admin_removed_registration_fields(): void
    {
        $this->enableGoshenRetreat();
        [$event] = $this->publishedRetreatEvent();
        app(GoshenRegistrationFieldService::class)->ensureDefaultsForEvent($event);

        EventAttendeeField::query()
            ->where('event_id', $event->id)
            ->where('key', 'company')
            ->delete();

        $response = $this->getJson('/api/goshen-retreat/events')
            ->assertOk();

        $fields = collect($response->json('data.0.attendee_fields'))->keyBy('key');

        $this->assertFalse($fields->has('company'));
        $this->assertTrue($fields->has('designation'));
    }

    public function test_booking_validates_dynamic_field_options_and_persists_custom_fields(): void
    {
        $this->enableGoshenRetreat();
        $user = $this->verifiedMember();
        $token = $user->issueApiToken();
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 0);
        app(GoshenRegistrationFieldService::class)->ensureDefaultsForEvent($event);

        EventAttendeeField::query()->create([
            'event_id' => $event->id,
            'key' => 'arrival_window',
            'label' => 'Arrival window',
            'type' => 'select',
            'is_required' => true,
            'is_unique' => false,
            'options' => [
                ['label' => 'Morning', 'value' => 'morning'],
                ['label' => 'Evening', 'value' => 'evening'],
            ],
            'sort_order' => 15,
        ]);

        $payload = [
            'data' => [
                'api_token' => $token,
                'event_id' => $event->public_id,
                'ticket_type_id' => $ticketType->public_id,
                'quantity' => 1,
                'uk_privacy_consent' => true,
                'privacy_policy_version' => 'uk-gdpr-2026-06',
                'attendees' => [
                    [
                        'first_name' => 'Member',
                        'last_name' => 'Field',
                        'designation' => 'worker',
                        'gender' => 'male',
                        'age_group' => 'adult',
                        'free_church_bus_interest' => 'yes',
                        'volunteer_department' => 'media',
                        'custom_fields' => [
                            'arrival_window' => 'midnight',
                        ],
                    ],
                ],
            ],
        ];

        $invalidResponse = $this->postJson('/api/goshen-retreat/bookings', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->assertSame(
            'Please select a valid Arrival window for attendee 1.',
            $invalidResponse->json('errors')['attendees.0.arrival_window'] ?? null,
        );

        $payload['data']['attendees'][0]['custom_fields']['arrival_window'] = 'morning';

        $this->postJson('/api/goshen-retreat/bookings', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value)
            ->assertJsonPath('booking.attendees.0.designation', 'worker')
            ->assertJsonPath('booking.attendees.0.free_church_bus_interest', 'yes');

        $booking = Booking::query()->where('customer_id', $user->id)->latest()->firstOrFail();
        $attendee = Attendee::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame('worker', $attendee->designation);
        $this->assertSame('worker', $attendee->custom_fields['designation'] ?? null);
        $this->assertSame('morning', $attendee->custom_fields['arrival_window'] ?? null);
        $this->assertSame('yes', $attendee->custom_fields['free_church_bus_interest'] ?? null);
    }

    public function test_booking_adds_paid_registration_option_fees_to_total(): void
    {
        $this->enableGoshenRetreat();
        $user = $this->verifiedMember();
        $token = $user->issueApiToken();
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 100);
        app(GoshenRegistrationFieldService::class)->ensureDefaultsForEvent($event);

        EventAttendeeField::query()->create([
            'event_id' => $event->id,
            'key' => 'retreat_shirt',
            'label' => 'Retreat shirt',
            'type' => 'select',
            'is_required' => true,
            'is_unique' => false,
            'options' => [
                ['label' => 'No shirt', 'value' => 'none'],
                ['label' => 'Medium shirt', 'value' => 'medium', 'fee_amount' => 25, 'fee_label' => 'Retreat shirt'],
            ],
            'sort_order' => 15,
        ]);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => [
                'api_token' => $token,
                'event_id' => $event->public_id,
                'ticket_type_id' => $ticketType->public_id,
                'quantity' => 1,
                'uk_privacy_consent' => true,
                'privacy_policy_version' => 'uk-gdpr-2026-06',
                'attendees' => [
                    [
                        'first_name' => 'Member',
                        'last_name' => 'Field',
                        'designation' => 'worker',
                        'gender' => 'male',
                        'age_group' => 'adult',
                        'free_church_bus_interest' => 'no_thanks',
                        'volunteer_department' => 'media',
                        'custom_fields' => [
                            'retreat_shirt' => 'medium',
                        ],
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('booking.subtotal', 125)
            ->assertJsonPath('booking.total', 125)
            ->assertJsonPath('booking.ticket_subtotal', 100)
            ->assertJsonPath('booking.selected_option_fee_total', 25)
            ->assertJsonPath('booking.selected_option_fees.0.label', 'Retreat shirt');

        $booking = Booking::query()->where('customer_id', $user->id)->latest()->firstOrFail();

        $this->assertSame(125.0, (float) $booking->total);
        $this->assertSame(25.0, (float) ($booking->metadata['selected_option_fee_total'] ?? 0));
    }

    private function enableGoshenRetreat(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );
    }

    private function verifiedMember(): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Member Test',
            'title' => 'Mr.',
            'email' => 'member@example.test',
            'phone' => '+2348011112222',
            'password' => 'secret',
            'gender' => 'male',
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
            'slug' => 'goshen-retreat-2026',
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => [],
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
}
