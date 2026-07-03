<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketCheckIn;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenScannerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_manifest_is_post_only_and_requires_scanner_auth(): void
    {
        $event = $this->publishedRetreatEvent();

        $this->getJson("/api/goshen-retreat/events/{$event->public_id}/scanner-manifest")
            ->assertMethodNotAllowed();

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/scanner-manifest", [
            'data' => ['api_token' => ''],
        ])
            ->assertUnauthorized()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'error');
    }

    public function test_scanner_role_receives_minimized_ticket_lookup_payload_without_contact_fields(): void
    {
        [$event, $ticket] = $this->ticketFixture();
        $scanner = $this->scannerUser();
        $token = $scanner->issueApiToken();

        $response = $this->postJson('/api/goshen-retreat/scanner/lookup', [
            'data' => [
                'api_token' => $token,
                'lookup_mode' => 'ticket',
                'identifier' => $ticket->formatted_number,
            ],
        ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.public_id', $ticket->public_id)
            ->assertJsonPath('data.attendee_name', 'Grace Test');

        $payload = $response->json('data');

        $this->assertScannerPayloadIsMinimized($payload);
        $this->assertSame($event->public_id, $payload['event']['public_id']);
    }

    public function test_scanner_manifest_includes_expiry_metadata_and_minimized_tickets(): void
    {
        [$event, $ticket] = $this->ticketFixture();
        $scanner = $this->scannerUser();
        $token = $scanner->issueApiToken();

        $response = $this->postJson("/api/goshen-retreat/events/{$event->public_id}/scanner-manifest", [
            'data' => ['api_token' => $token],
        ])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.ttl_seconds', 86400)
            ->assertJsonPath('data.manifest_version', 1)
            ->assertJsonPath('data.tickets.0.public_id', $ticket->public_id);

        $this->assertNotEmpty($response->json('data.generated_at'));
        $this->assertNotEmpty($response->json('data.expires_at'));

        $ticketPayload = $response->json('data.tickets.0');
        $this->assertScannerPayloadIsMinimized($ticketPayload);
    }

    public function test_public_events_endpoint_only_lists_goshen_retreat_editions(): void
    {
        $goshenEvent = $this->publishedRetreatEvent();
        $nonGoshenEvent = $this->publishedNonGoshenEvent();

        $response = $this->getJson('/api/goshen-retreat/events')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $goshenEvent->public_id);

        $eventIds = collect($response->json('data'))->pluck('public_id');

        $this->assertTrue($eventIds->contains($goshenEvent->public_id));
        $this->assertFalse($eventIds->contains($nonGoshenEvent->public_id));
    }

    public function test_scanner_lookup_rejects_non_goshen_ticket_identifier(): void
    {
        [, $ticket] = $this->ticketFixture($this->publishedNonGoshenEvent(), '000777', 'CONF-000777');
        $scanner = $this->scannerUser();
        $token = $scanner->issueApiToken();

        $this->postJson('/api/goshen-retreat/scanner/lookup', [
            'data' => [
                'api_token' => $token,
                'lookup_mode' => 'ticket',
                'identifier' => $ticket->formatted_number,
            ],
        ])
            ->assertNotFound()
            ->assertJsonPath('status', 'error');
    }

    public function test_scanner_name_search_excludes_non_goshen_tickets(): void
    {
        $this->ticketFixture($this->publishedNonGoshenEvent(), '000778', 'CONF-000778');
        $scanner = $this->scannerUser();
        $token = $scanner->issueApiToken();

        $this->postJson('/api/goshen-retreat/scanner/lookup', [
            'data' => [
                'api_token' => $token,
                'lookup_mode' => 'name',
                'query' => 'Grace',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.count', 0)
            ->assertJsonCount(0, 'data.matches');
    }

    public function test_scanner_manifest_rejects_non_goshen_event(): void
    {
        $event = $this->publishedNonGoshenEvent();
        $scanner = $this->scannerUser();
        $token = $scanner->issueApiToken();

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/scanner-manifest", [
            'data' => ['api_token' => $token],
        ])
            ->assertNotFound();
    }

    public function test_scanner_sync_rejects_non_goshen_ticket_without_creating_check_in(): void
    {
        [, $ticket] = $this->ticketFixture($this->publishedNonGoshenEvent(), '000779', 'CONF-000779');
        $scanner = $this->scannerUser();
        $token = $scanner->issueApiToken();

        $this->postJson('/api/goshen-retreat/scanner/sync', [
            'data' => [
                'api_token' => $token,
                'items' => [
                    [
                        'local_id' => 'offline-non-goshen',
                        'identifier' => $ticket->formatted_number,
                        'day_number' => 1,
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.synced', 0)
            ->assertJsonPath('data.rejected', 1)
            ->assertJsonPath('data.results.0.status', 'rejected');

        $this->assertSame(0, TicketCheckIn::query()
            ->where('ticket_id', $ticket->id)
            ->count());
    }

    public function test_scanner_role_cannot_use_internal_raw_ticket_browser_endpoints(): void
    {
        [$event, $ticket] = $this->ticketFixture();
        $scanner = $this->scannerUser();

        Sanctum::actingAs($scanner, ['*'], 'mobile');

        $eventTicketsResponse = $this->getJson("/api/v1/goshen-retreat/internal/events/{$event->id}/tickets");
        $eventTicketsResponse->assertStatus(in_array($eventTicketsResponse->status(), [403, 404], true) ? $eventTicketsResponse->status() : 403);

        $ticketResponse = $this->getJson("/api/v1/goshen-retreat/internal/tickets/{$ticket->formatted_number}");
        $ticketResponse->assertStatus(in_array($ticketResponse->status(), [403, 404], true) ? $ticketResponse->status() : 403);

        $this->assertScannerPayloadIsMinimized($eventTicketsResponse->json() ?? []);
        $this->assertScannerPayloadIsMinimized($ticketResponse->json() ?? []);
    }

    public function test_ticket_check_in_is_idempotent_per_ticket_day(): void
    {
        [, $ticket] = $this->ticketFixture();
        $scanner = $this->scannerUser();

        Sanctum::actingAs($scanner, ['*'], 'mobile');

        $payload = [
            'status' => TicketStatus::CheckedIn->value,
            'source' => 'flutter_scanner',
            'device_id' => 'scanner-device-1',
            'metadata' => ['scan_mode' => 'online'],
        ];

        $this->postJson("/api/v1/goshen-retreat/internal/tickets/{$ticket->formatted_number}/check-ins", $payload)
            ->assertSuccessful();

        $this->postJson("/api/v1/goshen-retreat/internal/tickets/{$ticket->formatted_number}/check-ins", $payload)
            ->assertSuccessful();

        $this->assertSame(1, TicketCheckIn::query()
            ->where('ticket_id', $ticket->id)
            ->where('day_number', 1)
            ->count());
    }

    public function test_ticket_check_in_allows_one_record_per_distinct_event_day(): void
    {
        [, $ticket] = $this->ticketFixture();
        $scanner = $this->scannerUser();

        Sanctum::actingAs($scanner, ['*'], 'mobile');

        $payload = [
            'status' => TicketStatus::CheckedIn->value,
            'source' => 'flutter_scanner',
            'device_id' => 'scanner-device-1',
        ];

        $this->postJson("/api/v1/goshen-retreat/internal/tickets/{$ticket->formatted_number}/days/1/check-ins", $payload)
            ->assertSuccessful();

        $this->postJson("/api/v1/goshen-retreat/internal/tickets/{$ticket->formatted_number}/days/2/check-ins", $payload)
            ->assertSuccessful();

        $this->assertSame(2, TicketCheckIn::query()
            ->where('ticket_id', $ticket->id)
            ->count());
    }

    private function publishedRetreatEvent(): Event
    {
        $event = Event::create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026',
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => [],
        ]);

        EventSchedule::create([
            'event_id' => $event->id,
            'day_number' => 1,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'metadata' => ['title' => 'Opening service'],
        ]);

        return $event->refresh();
    }

    private function publishedNonGoshenEvent(): Event
    {
        $event = Event::create([
            'name' => 'General Ministry Conference 2026',
            'slug' => 'general-ministry-conference-2026',
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => ['module' => 'generic_event'],
        ]);

        EventSchedule::create([
            'event_id' => $event->id,
            'day_number' => 1,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'metadata' => ['title' => 'Conference opening'],
        ]);

        return $event->refresh();
    }

    /**
     * @return array{0: Event, 1: Ticket}
     */
    private function ticketFixture(?Event $event = null, string $ticketNumber = '000001', string $formattedNumber = 'GOSHEN-000001'): array
    {
        $event ??= $this->publishedRetreatEvent();

        $ticketType = EventTicketType::create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'sku' => 'ADULT',
            'currency' => 'NGN',
            'price' => 0,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'event_id' => $event->id,
            'customer_name' => 'Grace Test',
            'customer_email' => 'grace@example.test',
            'customer_phone' => '+2348011112222',
            'currency' => 'NGN',
            'subtotal' => 0,
            'total' => 0,
            'paid_total' => 0,
            'status' => BookingStatus::Paid,
        ]);

        $attendee = Attendee::create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Grace',
            'last_name' => 'Test',
            'email' => 'grace.attendee@example.test',
            'phone' => '+2348099998888',
            'custom_fields' => [
                'gender' => 'female',
                'age_group' => 'adult',
                'medical_note' => 'private note',
            ],
        ]);

        $ticket = Ticket::create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => $ticketNumber,
            'formatted_number' => $formattedNumber,
            'qr_hash' => 'scanner-test-ticket-hash-' . $ticketNumber,
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
        ]);

        return [$event, $ticket->refresh()];
    }

    private function scannerUser(): MobileUser
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_scanner_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        $scanner = MobileUser::create([
            'name' => 'Scanner User',
            'email' => 'scanner@example.test',
            'password' => 'secret',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);

        Role::findOrCreate('event_scanner', 'mobile');
        $scanner->assignRole('event_scanner');

        return $scanner;
    }

    private function assertScannerPayloadIsMinimized(array $payload): void
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        foreach ([
            'customer_email',
            'customer_phone',
            'grace@example.test',
            'grace.attendee@example.test',
            '+2348011112222',
            '+2348099998888',
            'medical_note',
            'private note',
            'email',
            'phone',
        ] as $sensitiveValue) {
            $this->assertStringNotContainsString($sensitiveValue, $encoded);
        }
    }
}
