<?php

namespace Tests\Feature;

use App\Models\GoshenFamily;
use App\Models\GoshenFamilyMember;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenExistingFamilyLinkService;
use App\Services\GoshenVoucherService;
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

class GoshenExistingFamilyChildAdditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_adds_one_complimentary_child_without_touching_legacy_parent_records(): void
    {
        [$admin, $family, $parent, $ticketType, $parentTickets] = $this->familyFixture();

        $ticket = app(GoshenExistingFamilyLinkService::class)->addChild($admin, $family, [
            'child' => [
                'first_name' => 'Hope',
                'last_name' => 'Adeola',
                'date_of_birth' => now()->subYears(10)->toDateString(),
            ],
        ]);

        $this->assertSame(3, Ticket::query()->count());
        $this->assertSame(3, Attendee::query()->count());
        $this->assertSame(3, Booking::query()->count());
        $this->assertSame(BookingStatus::Paid, $ticket->booking->status);
        $this->assertSame('0.00', $ticket->booking->total);
        $this->assertSame(0, $ticket->booking->installments()->count());
        $this->assertSame(TicketStatus::NotCheckedIn, $ticket->status);
        $this->assertSame($parentTickets['father']->ticket_number, $parentTickets['father']->fresh()->ticket_number);
        $this->assertSame($parentTickets['mother']->booking_id, $parentTickets['mother']->fresh()->booking_id);
        $this->assertSame(3, $family->fresh()->members()->count());
        $this->assertDatabaseHas('goshen_family_members', ['goshen_family_id' => $family->id, 'attendee_id' => $ticket->attendee_id, 'role' => 'child']);
        $this->assertDatabaseHas('ei_event_audit_logs', ['event_id' => $family->event_id, 'actor_id' => $admin->id, 'action' => 'goshen_existing_family_child_added']);
    }

    public function test_paid_child_uses_a_positive_voucher_and_adult_child_gets_a_profile(): void
    {
        [$admin, $family, $parent, $ticketType] = $this->familyFixture();
        $voucher = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $family->event_id,
            'currency' => 'GBP',
            'amount' => 300,
            'max_uses' => 1,
        ], adminActor: $admin);

        $ticket = app(GoshenExistingFamilyLinkService::class)->addChild($admin, $family, [
            'ticket_type_id' => $ticketType->id,
            'issuance_reason' => 'Church office verified the adult child details.',
            'payment_method' => 'voucher',
            'voucher_code' => $voucher['code'],
            'child' => [
                'first_name' => 'Peace',
                'last_name' => 'Adeola',
                'date_of_birth' => now()->subYears(18)->toDateString(),
                'email' => 'peace.adeola@example.test',
                'phone' => '+447700000999',
                'adult_confirmation' => true,
            ],
        ]);

        $member = MobileUser::query()->where('email', 'peace.adeola@example.test')->sole();
        $this->assertSame(BookingStatus::Paid, $ticket->booking->status);
        $this->assertSame('300.00', $ticket->booking->total);
        $this->assertSame($member->id, GoshenFamilyMember::query()->where('attendee_id', $ticket->attendee_id)->value('mobile_user_id'));
        $this->assertSame(1, $ticket->booking->installments()->firstOrFail()->transactions()->where('gateway', 'voucher')->count());
    }

    public function test_it_rejects_cross_event_and_duplicate_child_additions(): void
    {
        [$admin, $family, $parent, $ticketType] = $this->familyFixture();
        $service = app(GoshenExistingFamilyLinkService::class);
        $child = ['first_name' => 'Faith', 'last_name' => 'Adeola', 'date_of_birth' => now()->subYears(8)->toDateString()];

        try {
            $service->addChild($admin, $family, ['ticket_type_id' => $this->ticketType($this->event('other'))->id, 'child' => $child]);
            $this->fail('Expected cross-event validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket_type_id', $exception->errors());
        }

        $service->addChild($admin, $family, ['child' => $child]);

        try {
            $service->addChild($admin, $family, ['child' => $child]);
            $this->fail('Expected duplicate-child validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('child', $exception->errors());
        }
    }

    /** @return array{User, GoshenFamily, MobileUser, EventTicketType, array{father: Ticket, mother: Ticket}} */
    private function familyFixture(): array
    {
        $event = $this->event();
        $ticketType = $this->ticketType($event);
        $admin = User::factory()->create();
        $parent = MobileUser::query()->create([
            'name' => 'David Adeola', 'first_name' => 'David', 'last_name' => 'Adeola', 'email' => 'david@example.test', 'phone' => '447700000001',
            'member_type' => 'church_member', 'is_verified' => true, 'is_blocked' => false, 'is_deleted' => false,
        ]);
        $family = GoshenFamily::query()->create(['event_id' => $event->id, 'name' => "Adeola's Family"]);
        $father = $this->paidTicket($event, $ticketType, 'David', 'Adeola', 'david@example.test', '447700000001', $parent->id);
        $mother = $this->paidTicket($event, $ticketType, 'Grace', 'Adeola', 'grace@example.test', '447700000002');
        foreach ([['father', $father, $parent->id], ['mother', $mother, null]] as [$role, $ticket, $mobileUserId]) {
            GoshenFamilyMember::query()->create([
                'goshen_family_id' => $family->id, 'attendee_id' => $ticket->attendee_id, 'mobile_user_id' => $mobileUserId,
                'role' => $role, 'first_name' => $ticket->attendee->first_name, 'last_name' => $ticket->attendee->last_name,
                'email' => $ticket->attendee->email, 'phone' => $ticket->attendee->phone,
            ]);
        }

        return [$admin, $family, $parent, $ticketType, ['father' => $father, 'mother' => $mother]];
    }

    private function event(string $suffix = '2026'): Event
    {
        return Event::query()->create(['name' => "Goshen {$suffix}", 'slug' => "goshen-{$suffix}", 'timezone' => 'Africa/Lagos', 'status' => 'published']);
    }

    private function ticketType(Event $event): EventTicketType
    {
        return EventTicketType::query()->create(['event_id' => $event->id, 'name' => 'Goshen Individual', 'currency' => 'GBP', 'price' => 300, 'is_active' => true]);
    }

    private function paidTicket(Event $event, EventTicketType $ticketType, string $firstName, string $lastName, string $email, string $phone, ?int $customerId = null): Ticket
    {
        $booking = Booking::query()->create([
            'event_id' => $event->id, 'customer_id' => $customerId, 'customer_name' => "{$firstName} {$lastName}", 'customer_email' => $email, 'customer_phone' => $phone,
            'currency' => 'GBP', 'subtotal' => 300, 'total' => 300, 'paid_total' => 300, 'status' => BookingStatus::Paid,
        ]);
        $attendee = Attendee::query()->create(['booking_id' => $booking->id, 'ticket_type_id' => $ticketType->id, 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email, 'phone' => $phone]);

        return Ticket::query()->create([
            'event_id' => $event->id, 'booking_id' => $booking->id, 'attendee_id' => $attendee->id, 'ticket_type_id' => $ticketType->id,
            'ticket_number' => (string) ($booking->id + 1000), 'formatted_number' => 'GOS-'.$booking->id, 'qr_hash' => hash('sha256', 'legacy-'.$booking->id),
            'status' => TicketStatus::NotCheckedIn, 'issued_at' => now(),
        ])->load('attendee');
    }
}
