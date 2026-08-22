<?php

namespace Tests\Feature;

use App\Filament\Resources\GoshenTicketResource\Pages\ListGoshenTickets;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketCheckIn;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenTicketCheckInStatusColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_list_shows_check_in_status_from_the_check_in_history(): void
    {
        [$event, $ticketType] = $this->eventFixture();

        $uncheckedTicket = $this->ticketFixture($event, $ticketType, '000100');
        $checkedInOnDayTwoTicket = $this->ticketFixture($event, $ticketType, '000101');

        TicketCheckIn::query()->create([
            'ticket_id' => $checkedInOnDayTwoTicket->id,
            'event_id' => $event->id,
            'day_number' => 2,
            'status' => TicketStatus::CheckedIn,
            'checked_in_at' => now(),
            'source' => 'scanner',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));

        Livewire::actingAs($admin)
            ->test(ListGoshenTickets::class)
            ->assertTableColumnExists('check_in_status')
            ->assertTableColumnStateSet('check_in_status', false, $uncheckedTicket)
            ->assertTableColumnFormattedStateSet('check_in_status', 'Not checked in', $uncheckedTicket)
            ->assertTableColumnStateSet('check_in_status', true, $checkedInOnDayTwoTicket)
            ->assertTableColumnFormattedStateSet('check_in_status', 'Checked in', $checkedInOnDayTwoTicket);
    }

    /** @return array{Event, EventTicketType} */
    private function eventFixture(): array
    {
        $event = Event::query()->create([
            'name' => 'Goshen Camp Retreat 2026',
            'slug' => 'goshen-camp-retreat-2026',
            'status' => 'published',
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Goshen Individual',
            'sku' => 'GOSHEN-IND',
            'currency' => 'GBP',
            'price' => 300,
            'is_active' => true,
        ]);

        return [$event, $ticketType];
    }

    private function ticketFixture(Event $event, EventTicketType $ticketType, string $ticketNumber): Ticket
    {
        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_name' => 'Grace Buyer',
            'customer_email' => 'buyer-'.$ticketNumber.'@example.test',
            'customer_phone' => '+447700'.$ticketNumber,
            'currency' => 'GBP',
            'subtotal' => 300,
            'total' => 300,
            'paid_total' => 300,
            'status' => BookingStatus::Paid,
        ]);

        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Grace',
            'last_name' => 'Buyer',
            'email' => 'grace-'.$ticketNumber.'@example.test',
        ]);

        return Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => $ticketNumber,
            'formatted_number' => 'GOSHEN-2-'.$ticketNumber,
            'qr_hash' => hash('sha256', $ticketNumber),
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
        ]);
    }
}
