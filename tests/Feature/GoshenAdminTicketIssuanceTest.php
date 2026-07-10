<?php

namespace Tests\Feature;

use App\Filament\Pages\GoshenRetreatConsole;
use App\Filament\Resources\GoshenTicketResource;
use App\Filament\Resources\GoshenTicketResource\Pages\CreateGoshenTicket;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenAdminTicketIssuanceService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAuditLog;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenAdminTicketIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_goshen_tickets_expose_an_admin_creation_page(): void
    {
        $this->assertArrayHasKey('create', GoshenTicketResource::getPages());
    }

    public function test_admin_ticket_issuance_has_a_dedicated_domain_service(): void
    {
        $this->assertTrue(class_exists(GoshenAdminTicketIssuanceService::class));
    }

    public function test_ticket_issuance_permission_is_available_to_admin_roles(): void
    {
        $this->assertArrayHasKey('goshen_ticket.issue', AdminPermissions::all());
    }

    public function test_admin_can_issue_a_complimentary_ticket_to_an_app_member(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();

        $ticket = app(GoshenAdminTicketIssuanceService::class)->issue(
            $member,
            $ticketType,
            $admin,
            'Pastoral allocation',
        );

        $booking = $ticket->booking()->with(['lines', 'attendees'])->firstOrFail();

        $this->assertSame($member->id, $booking->customer_id);
        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertSame('150.00', $booking->subtotal);
        $this->assertSame('0.00', $booking->total);
        $this->assertSame('0.00', $booking->paid_total);
        $this->assertSame('filament_admin', $booking->metadata['source']);
        $this->assertTrue($booking->metadata['complimentary']);
        $this->assertSame($admin->id, $booking->metadata['issued_by_admin_id']);
        $this->assertSame('Pastoral allocation', $booking->metadata['issuance_reason']);
        $this->assertCount(1, $booking->lines);
        $this->assertSame('150.00', $booking->lines->first()->unit_price);
        $this->assertSame('150.00', $booking->lines->first()->line_total);
        $this->assertCount(1, $booking->attendees);
        $this->assertSame($member->email, $booking->attendees->first()->email);
        $this->assertSame($booking->attendees->first()->id, $ticket->attendee_id);
        $this->assertSame(TicketStatus::NotCheckedIn, $ticket->status);
        $this->assertNotEmpty($ticket->formatted_number);
        $this->assertNotEmpty($ticket->qr_hash);
        $this->assertDatabaseHas(EventAuditLog::class, [
            'event_id' => $ticketType->event_id,
            'actor_id' => $admin->id,
            'action' => 'admin_ticket_issued',
            'auditable_type' => $ticket::class,
            'auditable_id' => $ticket->id,
        ]);
    }

    public function test_admin_cannot_issue_a_duplicate_ticket_for_the_same_member_event_and_type(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $service = app(GoshenAdminTicketIssuanceService::class);

        $service->issue($member, $ticketType, $admin, 'First allocation');

        $this->expectException(ValidationException::class);
        $service->issue($member, $ticketType, $admin, 'Duplicate allocation');
    }

    public function test_admin_cannot_issue_a_ticket_to_a_blocked_member(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $member->forceFill(['is_blocked' => true])->save();

        $this->expectException(ValidationException::class);

        app(GoshenAdminTicketIssuanceService::class)->issue(
            $member,
            $ticketType,
            $admin,
            'Blocked member allocation',
        );
    }

    public function test_admin_cannot_issue_a_ticket_for_an_unpublished_retreat(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $ticketType->event->forceFill(['status' => 'draft'])->save();

        $this->expectException(ValidationException::class);

        app(GoshenAdminTicketIssuanceService::class)->issue(
            $member,
            $ticketType,
            $admin,
            'Draft retreat allocation',
        );
    }

    public function test_any_admin_role_can_be_granted_ticket_issuance_without_delete_access(): void
    {
        $permission = Permission::findOrCreate(AdminPermissions::GOSHEN_TICKET_ISSUE, 'web');
        $role = Role::findOrCreate('ticket_desk', 'web');
        $role->givePermissionTo($permission);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin);

        $this->assertFalse(GoshenTicketResource::canViewAny());
        $this->assertTrue(GoshenTicketResource::canCreate());
        $this->assertFalse(GoshenTicketResource::canView(new Ticket));
        $this->assertFalse(GoshenTicketResource::canDelete(new Ticket));
        $this->assertTrue(GoshenRetreatConsole::canAccess());

        $ticketCard = collect((new GoshenRetreatConsole)->getViewData()['cards'])
            ->firstWhere('title', 'Tickets');

        $this->assertSame(GoshenTicketResource::getUrl('create'), $ticketCard['url']);
    }

    public function test_authorized_admin_can_issue_a_ticket_from_the_filament_form(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $permission = Permission::findOrCreate(AdminPermissions::GOSHEN_TICKET_ISSUE, 'web');
        $role = Role::findOrCreate('ticket_desk', 'web');
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        Livewire::actingAs($admin)
            ->test(CreateGoshenTicket::class)
            ->assertStatus(200)
            ->fillForm([
                'customer_id' => $member->id,
                'event_id' => $ticketType->event_id,
                'ticket_type_id' => $ticketType->id,
                'issuance_reason' => 'Approved pastoral allocation',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame($member->id, $ticket->booking->customer_id);
        $this->assertSame($ticketType->id, $ticket->ticket_type_id);
    }

    public function test_admin_without_ticket_permissions_cannot_open_the_issuance_page(): void
    {
        $permission = Permission::findOrCreate('manage_goshen_booking', 'web');
        $role = Role::findOrCreate('booking_desk', 'web');
        $role->givePermissionTo($permission);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        Livewire::actingAs($admin)
            ->test(CreateGoshenTicket::class)
            ->assertStatus(403);
    }

    public function test_issue_only_admin_cannot_open_existing_ticket_details(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $ticket = app(GoshenAdminTicketIssuanceService::class)->issue(
            $member,
            $ticketType,
            $admin,
            'Private ticket allocation',
        );
        $permission = Permission::findOrCreate(AdminPermissions::GOSHEN_TICKET_ISSUE, 'web');
        $role = Role::findOrCreate('ticket_desk', 'web');
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $this->actingAs($admin)
            ->get(GoshenTicketResource::getUrl('view', ['record' => $ticket]))
            ->assertForbidden();
    }

    /**
     * @return array{MobileUser, EventTicketType, User}
     */
    private function issuanceFixture(): array
    {
        $member = MobileUser::query()->create([
            'name' => 'Ada Lovelace',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+2348012345678',
            'is_verified' => true,
        ]);

        $event = Event::query()->create([
            'name' => 'Goshen 2026',
            'slug' => 'goshen-2026',
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'currency' => 'GBP',
            'price' => 150,
            'capacity' => 100,
            'is_active' => true,
        ]);

        $admin = User::factory()->create();

        return [$member, $ticketType, $admin];
    }
}
