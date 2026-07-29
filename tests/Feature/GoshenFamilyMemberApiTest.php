<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\GoshenAccommodationAllocation;
use App\Models\GoshenFamily;
use App\Models\GoshenFamilyMember;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Tests\TestCase;

class GoshenFamilyMemberApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_parents_receive_only_their_family_ticket_and_accommodation_summary(): void
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
            'settings' => [],
        ]);
        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Individual',
            'sku' => 'INDIVIDUAL',
            'currency' => 'GBP',
            'price' => 300,
            'is_active' => true,
        ]);
        $father = $this->member('father@example.test', 'David Adeola', '+447700000001');
        $mother = $this->member('mother@example.test', 'Grace Adeola', '+447700000002');
        $child = $this->member('child@example.test', 'Hope Adeola', '+447700000003');
        $father->forceFill(['avatar' => 'https://cdn.example.test/david.jpg'])->save();
        $mother->forceFill(['avatar' => 'https://cdn.example.test/grace.jpg'])->save();

        $fatherAttendee = $this->attendeeWithTicket($event, $ticketType, $father, 'David', 'Adeola', 'GOSHEN-1001');
        $motherAttendee = $this->attendeeWithTicket($event, $ticketType, $mother, 'Grace', 'Adeola', 'GOSHEN-1002');
        $childAttendee = $this->attendeeWithTicket($event, $ticketType, $child, 'Hope', 'Adeola', 'GOSHEN-1003');

        $family = GoshenFamily::query()->create([
            'event_id' => $event->id,
            'name' => 'Adeola Family',
        ]);
        $this->familyMember($family, $fatherAttendee, $father, 'father', 'David', 'Adeola');
        $this->familyMember($family, $motherAttendee, $mother, 'mother', 'Grace', 'Adeola');
        $this->familyMember($family, $childAttendee, $child, 'child', 'Hope', 'Adeola', ['age' => 10, 'gender' => 'female']);

        GoshenAccommodationAllocation::query()->create([
            'event_id' => $event->id,
            'attendee_id' => $motherAttendee->id,
            'ticket_id' => $motherAttendee->ticket->id,
            'status' => 'assigned',
            'building' => 'Mercy Hall',
            'room' => 'M-12',
            'bed' => 'B',
            'assigned_at' => now(),
            'check_in_note' => 'Internal desk note',
        ]);

        foreach ([$father, $mother] as $parent) {
            $response = $this->postJson('/api/goshen-retreat/me', [
                'data' => ['api_token' => $parent->issueApiToken()],
            ]);

            $response
                ->assertOk()
                ->assertJsonPath('data.family.name', 'Adeola Family')
                ->assertJsonCount(3, 'data.family.members')
                ->assertJsonPath('data.family.members.0.role', 'father')
                ->assertJsonPath('data.family.members.0.ticket.ticket_number', 'GOSHEN-1001')
                ->assertJsonPath('data.family.members.1.role', 'mother')
                ->assertJsonPath('data.family.members.1.avatar', 'https://cdn.example.test/grace.jpg')
                ->assertJsonPath('data.family.members.1.accommodation.status', 'assigned')
                ->assertJsonPath('data.family.members.1.accommodation.room', 'M-12')
                ->assertJsonPath('data.family.members.2.role', 'child')
                ->assertJsonPath('data.family.members.2.gender', 'female')
                ->assertJsonPath('data.family.members.2.accommodation.status', 'pending')
                ->assertJsonMissingPath('data.family.members.1.email')
                ->assertJsonMissingPath('data.family.members.1.phone')
                ->assertJsonMissingPath('data.family.members.1.accommodation.check_in_note');
        }
    }

    public function test_linked_child_with_a_profile_receives_the_family_summary(): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-child-scope',
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => [],
        ]);
        $child = $this->member('child-only@example.test', 'Hope Adeola', '+447700000004');
        $family = GoshenFamily::query()->create([
            'event_id' => $event->id,
            'name' => 'Adeola Family',
        ]);
        $this->familyMember($family, null, $child, 'child', 'Hope', 'Adeola');

        $this->postJson('/api/goshen-retreat/me', [
            'data' => ['api_token' => $child->issueApiToken()],
        ])
            ->assertOk()
            ->assertJsonPath('data.family.name', 'Adeola Family')
            ->assertJsonCount(1, 'data.family.members')
            ->assertJsonPath('data.family.members.0.role', 'child');
    }

    private function member(string $email, string $name, string $phone): MobileUser
    {
        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function attendeeWithTicket(
        Event $event,
        EventTicketType $ticketType,
        MobileUser $member,
        string $firstName,
        string $lastName,
        string $ticketNumber,
    ): Attendee {
        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
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
        ]);
        Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => $ticketNumber,
            'formatted_number' => $ticketNumber,
            'qr_hash' => strtolower($ticketNumber),
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
        ]);

        return $attendee->load('ticket');
    }

    /** @param array<string, mixed> $metadata */
    private function familyMember(
        GoshenFamily $family,
        ?Attendee $attendee,
        MobileUser $member,
        string $role,
        string $firstName,
        string $lastName,
        array $metadata = [],
    ): void {
        GoshenFamilyMember::query()->create([
            'goshen_family_id' => $family->id,
            'attendee_id' => $attendee?->id,
            'mobile_user_id' => $member->id,
            'role' => $role,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'metadata' => $metadata,
        ]);
    }
}
