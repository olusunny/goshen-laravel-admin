<?php

namespace Tests\Feature;

use App\Models\GoshenFamily;
use App\Models\GoshenFamilyMember;
use App\Models\User;
use App\Services\GoshenExistingFamilyLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAuditLog;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Tests\TestCase;

class GoshenExistingFamilyLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_link_paid_existing_tickets_without_changing_them(): void
    {
        $event = $this->event();
        $father = $this->ticket($event, 'David', 'Adeola');
        $mother = $this->ticket($event, 'Grace', 'Adeola');
        $child = $this->ticket($event, 'Hope', 'Adeola');
        $admin = User::factory()->create();

        $family = app(GoshenExistingFamilyLinkService::class)->link($admin, $event, "Adeola's Family", 'Backfill family relationship for accommodation planning.', [
            'father_ticket_id' => $father->id,
            'mother_ticket_id' => $mother->id,
            'children' => [['ticket_id' => $child->id]],
        ]);

        $this->assertNull($family->booking_id);
        $this->assertSame(['father', 'mother', 'child'], $family->members->pluck('role')->all());
        $this->assertSame($father->ticket_number, $father->fresh()->ticket_number);
        $this->assertSame(BookingStatus::Paid, $father->booking->fresh()->status);
        $this->assertDatabaseHas('ei_event_audit_logs', [
            'event_id' => $event->id,
            'actor_id' => $admin->id,
            'action' => 'goshen_existing_family_linked',
            'auditable_type' => GoshenFamily::class,
            'auditable_id' => $family->id,
        ]);
    }

    public function test_link_rejects_unpaid_cross_event_and_already_linked_tickets(): void
    {
        $event = $this->event();
        $otherEvent = $this->event('other');
        $father = $this->ticket($event, 'David', 'Adeola');
        $mother = $this->ticket($event, 'Grace', 'Adeola');
        $otherTicket = $this->ticket($otherEvent, 'Hope', 'Adeola');
        $admin = User::factory()->create();
        $service = app(GoshenExistingFamilyLinkService::class);

        try {
            $service->link($admin, $event, "Adeola's Family", 'Attempt with a ticket from another retreat.', [
                'father_ticket_id' => $father->id,
                'mother_ticket_id' => $otherTicket->id,
            ]);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('family', $exception->errors());
        }

        $family = $service->link($admin, $event, "Adeola's Family", 'Link the historical records after verification.', [
            'father_ticket_id' => $father->id,
            'mother_ticket_id' => $mother->id,
        ]);

        try {
            $service->link($admin, $event, 'Duplicate Family', 'Attempt to link the same paid attendee twice.', [
                'father_ticket_id' => $father->id,
                'mother_ticket_id' => $mother->id,
            ]);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('family', $exception->errors());
        }

        $this->assertSame(2, $family->members()->count());
    }

    public function test_unlink_removes_only_the_family_overlay_and_records_audit_event(): void
    {
        $event = $this->event();
        $father = $this->ticket($event, 'David', 'Adeola');
        $mother = $this->ticket($event, 'Grace', 'Adeola');
        $admin = User::factory()->create();
        $service = app(GoshenExistingFamilyLinkService::class);
        $family = $service->link($admin, $event, "Adeola's Family", 'Correct a historical family relationship.', [
            'father_ticket_id' => $father->id,
            'mother_ticket_id' => $mother->id,
        ]);

        $service->unlink($admin, $family, 'The tickets were linked to the wrong household.');

        $this->assertDatabaseMissing('goshen_families', ['id' => $family->id]);
        $this->assertDatabaseMissing('goshen_family_members', ['goshen_family_id' => $family->id]);
        $this->assertDatabaseHas('ei_tickets', ['id' => $father->id, 'ticket_number' => $father->ticket_number]);
        $this->assertDatabaseHas(EventAuditLog::class, [
            'event_id' => $event->id,
            'actor_id' => $admin->id,
            'action' => 'goshen_existing_family_unlinked',
            'auditable_type' => GoshenFamily::class,
            'auditable_id' => $family->id,
        ]);
    }

    private function event(string $suffix = ''): Event
    {
        return Event::query()->create([
            'name' => 'Goshen '.($suffix ?: '2026'),
            'slug' => 'goshen-'.($suffix ?: '2026'),
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);
    }

    private function ticket(Event $event, string $firstName, string $lastName): Ticket
    {
        $ticketType = EventTicketType::query()->firstOrCreate([
            'event_id' => $event->id,
            'name' => 'Adult',
        ], [
            'currency' => 'GBP',
            'price' => 300,
            'is_active' => true,
        ]);
        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_name' => "{$firstName} {$lastName}",
            'customer_email' => strtolower("{$firstName}.{$lastName}@example.test"),
            'currency' => 'GBP',
            'subtotal' => 300,
            'total' => 300,
            'paid_total' => 300,
            'status' => BookingStatus::Paid,
        ]);
        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower("{$firstName}.{$booking->id}@example.test"),
            'phone' => '+447700'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
        ]);

        return Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'GOS-'.$booking->id,
            'formatted_number' => 'GOS-'.$booking->id,
            'qr_hash' => hash('sha256', 'ticket-'.$booking->id),
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
        ]);
    }
}
