<?php

namespace Tests\Feature;

use App\Services\GoshenAccommodationEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Tests\TestCase;

class GoshenAccommodationEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_attendee_cannot_receive_accommodation_allocation(): void
    {
        [$event, $attendee] = $this->attendeeFixture(BookingStatus::Pending, TicketStatus::Unpaid);

        $this->expectException(ValidationException::class);

        app(GoshenAccommodationEligibility::class)->validateAndHydrateAllocationData([
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'status' => 'assigned',
            'building' => 'Mercy Hall',
        ]);
    }

    public function test_paid_attendee_with_active_ticket_can_receive_accommodation_allocation(): void
    {
        [$event, $attendee, $ticket] = $this->attendeeFixture(BookingStatus::Paid, TicketStatus::NotCheckedIn);

        $data = app(GoshenAccommodationEligibility::class)->validateAndHydrateAllocationData([
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'status' => 'assigned',
            'building' => 'Mercy Hall',
        ]);

        $this->assertSame($ticket->id, $data['ticket_id']);
        $this->assertNotEmpty($data['assigned_at']);
    }

    /**
     * @return array{0: Event, 1: Attendee, 2: Ticket}
     */
    private function attendeeFixture(BookingStatus $bookingStatus, TicketStatus $ticketStatus): array
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026-' . strtolower($bookingStatus->value) . '-' . strtolower($ticketStatus->value),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => [],
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'sku' => 'ADULT',
            'currency' => 'NGN',
            'price' => 1000,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_name' => 'Mercy Guest',
            'customer_email' => 'guest@example.test',
            'customer_phone' => '+2348011112222',
            'currency' => 'NGN',
            'subtotal' => 1000,
            'total' => 1000,
            'paid_total' => $bookingStatus === BookingStatus::Pending ? 0 : 1000,
            'status' => $bookingStatus,
        ]);

        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Mercy',
            'last_name' => 'Guest',
            'email' => 'guest.attendee@example.test',
            'phone' => '+2348099998888',
            'custom_fields' => ['gender' => 'female', 'age_group' => 'adult'],
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => '000001',
            'formatted_number' => 'GOSHEN-000001',
            'qr_hash' => 'accommodation-eligibility-' . $bookingStatus->value . '-' . $ticketStatus->value,
            'status' => $ticketStatus,
            'issued_at' => now(),
        ]);

        return [$event->refresh(), $attendee->refresh(), $ticket->refresh()];
    }
}
