<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\GoshenRetreatController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Services\TicketDocumentService;
use Tests\TestCase;

class GoshenComplimentaryChildTicketLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('event-installments.ticket.qr_secret', 'complimentary-child-ticket-test-secret');
    }

    public function test_payment_exempt_family_child_uses_the_complementary_label_in_live_and_pdf_ticket_rendering(): void
    {
        $ticket = $this->ticketFixture([
            'family_role' => 'child',
            'payment_exempt' => true,
        ]);

        $documents = app(TicketDocumentService::class);

        $this->assertStringContainsString(
            'Children Complementary Ticket',
            $this->invokePrivateMethod($documents, 'ticketHtml', $ticket),
        );
        $this->assertStringContainsString(
            'Amount paid: Children Complementary Ticket',
            $this->invokePrivateMethod($documents, 'basicTicketPdf', $ticket),
        );

        $payload = $this->invokePrivateMethod(app(GoshenRetreatController::class), 'ticketPayload', $ticket);

        $this->assertSame('Children Complementary Ticket', $payload['amount_paid_label']);
    }

    public function test_unpaid_ticket_that_is_not_payment_exempt_keeps_its_existing_labels(): void
    {
        $ticket = $this->ticketFixture([
            'family_role' => 'child',
            'payment_exempt' => false,
        ]);

        $documents = app(TicketDocumentService::class);

        $this->assertStringContainsString('Amount paid: Not recorded', $this->invokePrivateMethod($documents, 'basicTicketPdf', $ticket));

        $payload = $this->invokePrivateMethod(app(GoshenRetreatController::class), 'ticketPayload', $ticket);

        $this->assertSame('GBP 0.00', $payload['amount_paid_label']);
        $this->assertNotSame('Children Complementary Ticket', $payload['amount_paid_label']);
    }

    private function ticketFixture(array $metadata): Ticket
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-ticket-label-'.uniqid(),
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Goshen Family Child',
            'currency' => 'GBP',
            'price' => 300,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_name' => 'Parent Example',
            'customer_email' => 'parent@example.test',
            'currency' => 'GBP',
            'subtotal' => 0,
            'total' => 0,
            'paid_total' => 0,
            'status' => BookingStatus::Paid,
        ]);

        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Child',
            'last_name' => 'Example',
        ]);

        return Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'child-'.uniqid(),
            'formatted_number' => 'GOS-CHILD-'.uniqid(),
            'qr_hash' => hash('sha256', uniqid('ticket-', true)),
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    private function invokePrivateMethod(object $instance, string $method, mixed ...$arguments): mixed
    {
        $reflection = new \ReflectionMethod($instance, $method);

        return $reflection->invoke($instance, ...$arguments);
    }
}
