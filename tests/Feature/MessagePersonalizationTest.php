<?php

namespace Tests\Feature;

use App\Models\GoshenWallet;
use App\Models\InboxMessage;
use App\Models\MobileUser;
use App\Services\MessagePersonalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAttendeeField;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketCheckIn;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MessagePersonalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_personalization_supports_shorthand_user_and_dynamic_registration_tags(): void
    {
        $user = $this->member();
        GoshenWallet::query()->create([
            'mobile_user_id' => $user->id,
            'balance' => 42.5,
        ]);
        [$event] = $this->registration($user);
        $message = new InboxMessage(['goshen_event_id' => $event->id]);

        $rendered = app(MessagePersonalizationService::class)->renderText(
            'Hello {usertitle} {user firstname}. {User: goshen_registration_status}; {User: check-in_status_with_time}; {goshen_edition}; {User: designation}; {user: t_shirt_type}; {User: wallet_balance}; {user: triumphant_id}.',
            $user,
            $message,
        );

        $this->assertStringContainsString('Hello Mr. David.', $rendered);
        $this->assertStringContainsString('Paid', $rendered);
        $this->assertStringContainsString('Checked in Jul 3, 2026 16:15', $rendered);
        $this->assertStringContainsString('Goshen Retreat 2026', $rendered);
        $this->assertStringContainsString('Worker', $rendered);
        $this->assertStringContainsString('Large T-shirt', $rendered);
        $this->assertStringContainsString('GBP 42.50', $rendered);
        $this->assertStringContainsString('TRI-777', $rendered);
    }

    public function test_inbox_fetch_renders_message_for_current_recipient(): void
    {
        $user = $this->member();
        [$event] = $this->registration($user);
        $token = $user->issueApiToken();

        InboxMessage::query()->create([
            'title' => 'Hello {usertitle} {user firstname}',
            'content' => '<p>Your registration is {user: goshen_registration_status} for {goshen_edition}.</p>',
            'goshen_event_id' => $event->id,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->postJson('/fetch_inbox', [
            'data' => [
                'api_token' => $token,
                'email' => $user->email,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('inbox.0.title', 'Hello Mr. David')
            ->assertJsonPath('inbox.0.message', '<p>Your registration is Paid for Goshen Retreat 2026.</p>');
    }

    public function test_control_hub_message_options_include_personalization_tags(): void
    {
        $manager = $this->member('manager@example.test', 'Manager One');
        Permission::findOrCreate('send_admin_messages', 'mobile');
        $role = Role::findOrCreate('event_manager', 'mobile');
        $role->givePermissionTo('send_admin_messages');
        $manager->assignRole($role);
        $token = $manager->issueApiToken();

        EventAttendeeField::query()->create([
            'event_id' => $this->goshenEvent()->id,
            'key' => 'future_custom_field',
            'label' => 'Future custom field',
            'type' => 'text',
            'is_required' => false,
            'options' => [],
            'sort_order' => 10,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/messages/options', [
                'data' => ['api_token' => $token],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonFragment(['tag' => '{user: title}'])
            ->assertJsonFragment(['tag' => '{user: future_custom_field}']);
    }

    private function member(string $email = 'david@example.test', string $name = 'David Davis'): MobileUser
    {
        return MobileUser::query()->create([
            'name' => $name,
            'first_name' => str($name)->before(' ')->toString(),
            'last_name' => str($name)->after(' ')->toString() ?: 'Davis',
            'title' => 'Mr.',
            'marital_status' => 'Married',
            'triumphant_id' => 'TRI-777',
            'email' => $email,
            'phone' => '+447700900111',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function registration(MobileUser $user): array
    {
        $event = $this->goshenEvent();
        EventAttendeeField::query()->create([
            'event_id' => $event->id,
            'key' => 'designation',
            'label' => 'Designation',
            'type' => 'select',
            'is_required' => true,
            'options' => [
                ['label' => 'Worker', 'value' => 'worker'],
            ],
            'sort_order' => 10,
        ]);
        EventAttendeeField::query()->create([
            'event_id' => $event->id,
            'key' => 't_shirt_type',
            'label' => 'T-shirt type',
            'type' => 'select',
            'is_required' => false,
            'options' => [
                ['label' => 'Large T-shirt', 'value' => 'large'],
            ],
            'sort_order' => 20,
        ]);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'currency' => 'GBP',
            'subtotal' => 100,
            'total' => 100,
            'paid_total' => 100,
            'status' => BookingStatus::Paid,
        ]);
        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult registration',
            'sku' => 'ADULT',
            'currency' => 'GBP',
            'price' => 100,
            'capacity' => 100,
            'min_per_booking' => 1,
            'max_per_booking' => 10,
            'is_active' => true,
        ]);

        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'David',
            'last_name' => 'Davis',
            'email' => $user->email,
            'phone' => $user->phone,
            'designation' => 'worker',
            'custom_fields' => [
                'designation' => 'worker',
                't_shirt_type' => 'large',
            ],
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'TICKET-1',
            'formatted_number' => 'TICKET-1',
            'qr_hash' => 'ticket-hash-1',
            'status' => TicketStatus::NotCheckedIn,
        ]);

        TicketCheckIn::query()->create([
            'ticket_id' => $ticket->id,
            'event_id' => $event->id,
            'status' => TicketStatus::CheckedIn,
            'checked_in_at' => '2026-07-03 16:15:00',
            'source' => 'test',
        ]);

        return [$event, $booking, $attendee];
    }

    private function goshenEvent(): Event
    {
        return Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-'.str()->random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'settings' => ['module' => 'goshen_retreat'],
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
        ]);
    }
}
