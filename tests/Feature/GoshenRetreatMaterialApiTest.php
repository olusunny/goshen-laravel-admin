<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\GoshenRetreatMaterial;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenRetreatMaterialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_ticket_holders_can_list_and_download_published_materials(): void
    {
        Storage::fake('local');
        $holder = $this->member('holder@example.test');
        $otherMember = $this->member('other@example.test');
        $event = $this->event();
        $this->activeTicket($event, $holder);

        Storage::disk('local')->put('goshen/retreat/materials/guide.pdf', 'private material');
        $material = GoshenRetreatMaterial::query()->create([
            'event_id' => $event->id,
            'label' => 'Retreat guide',
            'file_path' => 'goshen/retreat/materials/guide.pdf',
            'filename' => 'retreat-guide.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 16,
            'is_published' => true,
        ]);

        $this->postJson('/api/goshen-retreat/materials', [
            'data' => ['api_token' => $holder->issueApiToken()],
        ])
            ->assertOk()
            ->assertJsonPath('data.materials.0.id', $material->id)
            ->assertJsonPath('data.materials.0.label', 'Retreat guide')
            ->assertJsonPath('data.materials.0.file_type', 'pdf');

        $this->postJson('/api/goshen-retreat/materials', [
            'data' => ['api_token' => $otherMember->issueApiToken()],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.materials');

        $this->post('/api/goshen-retreat/materials/'.$material->id.'/download', [
            'data' => ['api_token' => $otherMember->issueApiToken()],
        ])->assertNotFound();

        $this->post('/api/goshen-retreat/materials/'.$material->id.'/download', [
            'data' => ['api_token' => $holder->issueApiToken()],
        ])->assertOk()->assertDownload('retreat-guide.pdf');
    }

    public function test_registration_manager_can_upload_and_delete_event_material(): void
    {
        Storage::fake('local');
        $manager = $this->member('manager@example.test');
        $manager->assignRole(Role::findOrCreate('event_manager', 'mobile'));
        $event = $this->event();

        $response = $this->post('/api/goshen-retreat/events/'.$event->public_id.'/materials/save', [
            'data' => [
                'api_token' => $manager->issueApiToken(),
                'label' => 'Prayer outline',
                'is_published' => true,
            ],
            'file' => UploadedFile::fake()->create('prayer-outline.pdf', 10, 'application/pdf'),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.material.label', 'Prayer outline')
            ->assertJsonPath('data.material.filename', 'prayer-outline.pdf');

        $material = GoshenRetreatMaterial::query()->sole();
        Storage::disk('local')->assertExists($material->file_path);

        $this->postJson('/api/goshen-retreat/events/'.$event->public_id.'/materials/'.$material->id.'/delete', [
            'data' => ['api_token' => $manager->issueApiToken()],
        ])->assertOk();

        Storage::disk('local')->assertMissing($material->file_path);
        $this->assertDatabaseMissing('goshen_retreat_materials', ['id' => $material->id]);
    }

    private function member(string $email): MobileUser
    {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        return MobileUser::query()->create([
            'name' => 'Material Member',
            'email' => $email,
            'phone' => '+234801111'.str_pad((string) MobileUser::query()->count(), 4, '0', STR_PAD_LEFT),
            'password' => 'secret',
            'title' => 'Mr.',
            'gender' => 'male',
            'marital_status' => 'Married',
            'member_type' => 'church_member',
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Lagos',
            'address' => '1 Mercy Road, Lagos',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function event(): Event
    {
        return Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-materials-'.str()->random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => [],
        ]);
    }

    private function activeTicket(Event $event, MobileUser $user): void
    {
        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'sku' => 'MATERIAL-ADULT',
            'currency' => 'GBP',
            'price' => 300,
            'is_active' => true,
        ]);
        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'currency' => 'GBP',
            'subtotal' => 300,
            'total' => 300,
            'paid_total' => 300,
            'status' => BookingStatus::Paid,
        ]);
        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Material',
            'last_name' => 'Holder',
            'email' => $user->email,
        ]);
        Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'MATERIAL-001',
            'formatted_number' => 'GOSHEN-MATERIAL-001',
            'qr_hash' => 'materials-'.str()->random(20),
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
        ]);
    }
}
