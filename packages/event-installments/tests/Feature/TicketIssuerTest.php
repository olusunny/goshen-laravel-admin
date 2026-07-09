<?php

namespace Personal\EventInstallments\Tests\Feature;

use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Services\TicketIssuer;
use Personal\EventInstallments\Tests\TestCase;

class TicketIssuerTest extends TestCase
{
    public function test_it_issues_one_ticket_per_attendee_and_is_idempotent(): void
    {
        $event = Event::query()->create([
            'name' => 'Launch Night',
            'slug' => 'launch-night',
            'timezone' => 'UTC',
            'status' => 'published',
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'General',
            'currency' => 'USD',
            'price' => 100,
        ]);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_email' => 'ada@example.com',
            'currency' => 'USD',
            'subtotal' => 200,
            'total' => 200,
            'status' => BookingStatus::Paid,
        ]);

        Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Ada',
            'email' => 'ada@example.com',
        ]);

        Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Grace',
            'email' => 'grace@example.com',
        ]);

        $issuer = app(TicketIssuer::class);

        $this->assertCount(2, $issuer->issueForBooking($booking));
        $this->assertCount(0, $issuer->issueForBooking($booking->fresh()));
        $this->assertSame(2, $booking->tickets()->count());
    }
}
