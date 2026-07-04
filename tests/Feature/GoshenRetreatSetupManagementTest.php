<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAttendeeField;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenRetreatSetupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_manager_can_manage_retreat_setup_from_mobile_api(): void
    {
        [$event, $ticketType] = $this->publishedRetreatEvent();
        $manager = $this->manager();
        $token = $manager->issueApiToken();

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/setup/overview", [
            'data' => [
                'api_token' => $token,
                'name' => 'Goshen Retreat 2026 Updated',
                'slug' => 'goshen-retreat-2026-updated',
                'type' => 'sequential',
                'description' => 'Updated retreat description',
                'timezone' => 'Europe/London',
                'support_email' => 'retreat@example.test',
                'inquiry_phone' => '+447700900123',
                'venue_name' => 'Triumphant Centre',
                'venue_address' => 'London',
                'sales_start_at' => now()->subDay()->toIso8601String(),
                'sales_end_at' => now()->addMonth()->toIso8601String(),
                'registration_override' => 'open',
                'registration_close_reason' => '',
                'pay_in_full_discount' => [
                    'enabled' => true,
                    'label' => 'Early full payment',
                    'type' => 'fixed',
                    'value' => 30,
                    'starts_at' => now()->subDay()->toIso8601String(),
                    'ends_at' => now()->addWeek()->toIso8601String(),
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.event.name', 'Goshen Retreat 2026 Updated')
            ->assertJsonPath('data.event.pay_in_full_discount.label', 'Early full payment');

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/setup/schedules", [
            'data' => [
                'api_token' => $token,
                'day_number' => 2,
                'title' => 'Workshop',
                'starts_at' => now()->addDays(2)->toIso8601String(),
                'ends_at' => now()->addDays(2)->addHours(2)->toIso8601String(),
                'capacity' => 150,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertDatabaseHas('ei_event_schedules', [
            'event_id' => $event->id,
            'day_number' => 2,
            'capacity' => 150,
        ]);

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/setup/ticket-types", [
            'data' => [
                'api_token' => $token,
                'id' => $ticketType->public_id,
                'name' => 'Adult Plus',
                'sku' => 'ADULT-PLUS',
                'currency' => 'GBP',
                'price' => 270,
                'capacity' => 300,
                'min_per_booking' => 1,
                'max_per_booking' => 4,
                'is_active' => false,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.event.ticket_types.0.name', 'Adult Plus')
            ->assertJsonPath('data.event.ticket_types.0.is_active', false);

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/setup/registration-fields", [
            'data' => [
                'api_token' => $token,
                'key' => 'shirt_size',
                'label' => 'Shirt size',
                'type' => 'select',
                'is_required' => true,
                'sort_order' => 70,
                'options' => [
                    ['label' => 'Please Select', 'value' => '', 'sort_order' => 1],
                    ['label' => 'Medium', 'value' => 'medium', 'fee_amount' => 5, 'sort_order' => 2],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonFragment(['key' => 'shirt_size']);

        $field = EventAttendeeField::query()->where('key', 'shirt_size')->firstOrFail();

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/setup/registration-fields/{$field->id}/delete", [
            'data' => ['api_token' => $token],
        ])->assertOk();

        $this->assertDatabaseMissing('ei_event_attendee_fields', ['id' => $field->id]);
    }

    public function test_non_manager_cannot_manage_retreat_setup(): void
    {
        [$event] = $this->publishedRetreatEvent();
        $member = $this->verifiedMember('member@example.test', 'Plain Member', '+2348011114444');
        $token = $member->issueApiToken();

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/setup", [
            'data' => ['api_token' => $token],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');
    }

    public function test_used_ticket_type_must_be_deactivated_instead_of_deleted(): void
    {
        [$event, $ticketType] = $this->publishedRetreatEvent();
        $manager = $this->manager();
        $member = $this->verifiedMember('registered@example.test', 'Registered Member', '+2348011115555');

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'GBP',
            'subtotal' => 100,
            'total' => 100,
            'paid_total' => 100,
            'status' => 'paid',
        ]);

        BookingLine::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'currency' => 'GBP',
            'unit_price' => 100,
            'line_total' => 100,
        ]);

        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'full_name' => 'Registered Member',
            'email' => $member->email,
        ]);

        Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'TKT-001',
            'qr_hash' => 'retreat-setup-test-ticket-hash',
            'status' => 'not_checked_in',
        ]);

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/setup/ticket-types/{$ticketType->public_id}/delete", [
            'data' => ['api_token' => $manager->issueApiToken()],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('ei_event_ticket_types', ['id' => $ticketType->id]);
    }

    private function manager(): MobileUser
    {
        $manager = $this->verifiedMember('manager@example.test', 'Goshen Manager', '+2348011113333');
        Role::findOrCreate('event_manager', 'mobile');
        $manager->assignRole('event_manager');

        return $manager;
    }

    private function verifiedMember(
        string $email = 'member@example.test',
        string $name = 'Member Test',
        string $phone = '+2348011112222',
    ): MobileUser {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        return MobileUser::query()->create([
            'name' => $name,
            'title' => 'Mr.',
            'email' => $email,
            'phone' => $phone,
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
    private function publishedRetreatEvent(): array
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026',
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
            'currency' => 'GBP',
            'price' => 300,
            'min_per_booking' => 1,
            'max_per_booking' => 10,
            'is_active' => true,
        ]);

        return [$event->refresh(), $ticketType->refresh()];
    }
}
