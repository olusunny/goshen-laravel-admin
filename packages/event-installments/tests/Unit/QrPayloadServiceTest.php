<?php

namespace Personal\EventInstallments\Tests\Unit;

use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Services\QrPayloadService;
use Personal\EventInstallments\Tests\TestCase;

class QrPayloadServiceTest extends TestCase
{
    public function test_it_signs_and_verifies_ticket_payloads(): void
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
            'subtotal' => 100,
            'total' => 100,
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => '1',
            'formatted_number' => 'EVT-1-000001',
            'qr_hash' => hash('sha256', 'ticket'),
        ]);

        $service = app(QrPayloadService::class);
        $payload = $service->payloadFor($ticket->fresh('event'));

        $this->assertTrue($service->verify($payload));

        $payload['ticket'] = 'tampered';

        $this->assertFalse($service->verify($payload));
    }
}
