<?php

namespace Tests\Feature;

use App\Models\ChurchEvent;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ControlHubChurchEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authorized_mobile_manager_can_manage_church_events(): void
    {
        Storage::fake('public');

        $member = $this->member('member@example.test');
        $memberToken = $member->issueApiToken();

        $this->postJson('/api/control-hub/church-events', [
            'data' => [
                'api_token' => $memberToken,
                'title' => 'Unauthorized event',
            ],
        ])->assertForbidden();

        $manager = $this->member('manager@example.test');
        $manager->assignRole(Role::query()->firstOrCreate([
            'name' => 'event_manager',
            'guard_name' => 'mobile',
        ]));
        $managerToken = $manager->issueApiToken();

        $create = $this->post('/api/control-hub/church-events', [
            'data' => json_encode([
                'api_token' => $managerToken,
                'title' => 'Monthly Revival Service',
                'details' => 'A focused monthly revival service.',
                'venue' => 'Main Auditorium',
                'theme' => 'Revival',
                'starts_at' => '2026-09-06 10:00:00',
                'ends_at' => '2026-09-06 12:00:00',
                'registration_availability' => 'everywhere',
                'is_published' => false,
                'recurrence_type' => ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY,
                'recurrence_weekday' => 0,
                'recurrence_week_of_month' => 1,
                'recurrence_interval' => 1,
            ]),
            'thumbnail' => $this->fakePng('revival.png'),
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.title', 'Monthly Revival Service')
            ->assertJsonPath('data.recurrence_type', ChurchEvent::RECURRENCE_MONTHLY_NTH_WEEKDAY);

        $eventId = (int) $create->json('data.id');
        $event = ChurchEvent::query()->findOrFail($eventId);

        Storage::disk('public')->assertExists($event->thumbnail);

        $this->postJson("/api/control-hub/church-events/{$eventId}/status", [
            'data' => [
                'api_token' => $managerToken,
                'is_published' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->post("/api/control-hub/church-events/{$eventId}", [
            'data' => json_encode([
                'api_token' => $managerToken,
                'title' => 'Weekly Revival Service',
                'details' => 'Updated weekly service.',
                'venue' => 'Main Auditorium',
                'starts_at' => '2026-09-07 19:00:00',
                'is_published' => true,
                'recurrence_type' => ChurchEvent::RECURRENCE_WEEKLY,
                'recurrence_weekday' => 1,
                'recurrence_interval' => 1,
            ]),
            'portrait_image' => $this->fakePng('portrait.png'),
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Weekly Revival Service')
            ->assertJsonPath('data.recurrence_type', ChurchEvent::RECURRENCE_WEEKLY)
            ->assertJsonPath('data.recurrence_weekday', 1);

        $event->refresh();
        Storage::disk('public')->assertExists($event->portrait_image);

        $this->postJson("/api/control-hub/church-events/{$eventId}/delete", [
            'data' => ['api_token' => $managerToken],
        ])->assertOk();

        $this->assertDatabaseMissing('church_events', ['id' => $eventId]);
    }

    private function member(string $email): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Event Member',
            'email' => $email,
            'phone' => '+447700900123',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function fakePng(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        );
    }
}
