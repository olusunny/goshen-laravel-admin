<?php

namespace Tests\Feature;

use App\Models\CommunityPrayerRequest;
use App\Models\MobileUser;
use App\Models\PropheticDecree;
use App\Services\PrayerModerationNotifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrayerCommunityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_create_text_prayer_request_anonymously(): void
    {
        $user = MobileUser::create([
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'secret',
            'is_verified' => true,
        ]);
        $token = $user->issueApiToken();

        $this->postJson('/prayer-community', [
            'data' => [
                'api_token' => $token,
                'type' => 'text',
                'text' => 'Please pray for my family.',
                'is_anonymous' => true,
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('prayer_request.identity', 'Anonymous');

        $this->assertDatabaseHas('community_prayer_requests', [
            'mobile_user_id' => $user->id,
            'type' => 'text',
            'is_anonymous' => true,
        ]);
    }

    public function test_unverified_user_cannot_submit_or_comment(): void
    {
        $user = MobileUser::create([
            'name' => 'Ben',
            'email' => 'ben@example.test',
            'password' => 'secret',
            'is_verified' => false,
        ]);
        $token = $user->issueApiToken();
        $prayer = CommunityPrayerRequest::create([
            'mobile_user_id' => $user->id,
            'type' => 'text',
            'text' => 'Existing request',
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson('/prayer-community', ['data' => ['api_token' => $token, 'type' => 'text', 'text' => 'No']])
            ->assertForbidden();

        $this->postJson("/prayer-community/{$prayer->id}/comments", ['data' => ['api_token' => $token, 'text' => 'I pray for you']])
            ->assertForbidden();
    }

    public function test_verified_user_can_submit_only_one_prayer_request_per_twenty_four_hours(): void
    {
        $user = MobileUser::create([
            'name' => 'Limit',
            'email' => 'limit@example.test',
            'password' => 'secret',
            'is_verified' => true,
        ]);
        $token = $user->issueApiToken();

        $this->postJson('/prayer-community', [
            'data' => [
                'api_token' => $token,
                'type' => 'text',
                'text' => 'First prayer',
            ],
        ])->assertCreated();

        $this->postJson('/prayer-community', [
            'data' => [
                'api_token' => $token,
                'type' => 'text',
                'text' => 'Second prayer',
            ],
        ])
            ->assertStatus(429)
            ->assertJsonPath('can_submit_prayer', false)
            ->assertJsonStructure(['next_available_at', 'cooldown_seconds']);
    }

    public function test_three_unique_flags_hide_prayer_request_and_duplicate_flags_are_rejected(): void
    {
        $notifier = \Mockery::mock(PrayerModerationNotifier::class);
        $notifier->shouldReceive('notifyAutoHidden')->once();
        $this->app->instance(PrayerModerationNotifier::class, $notifier);

        $owner = MobileUser::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'secret', 'is_verified' => true]);
        $prayer = CommunityPrayerRequest::create([
            'mobile_user_id' => $owner->id,
            'type' => 'text',
            'text' => 'Public request',
            'expires_at' => now()->addDay(),
        ]);

        $lastFlaggerToken = null;

        foreach (range(1, 3) as $index) {
            $flagger = MobileUser::create(['name' => "Flag {$index}", 'email' => "flag{$index}@example.test", 'password' => 'secret', 'is_verified' => true]);
            $lastFlaggerToken = $flagger->issueApiToken();
            $this->postJson("/prayer-community/{$prayer->id}/flags", [
                'data' => ['api_token' => $lastFlaggerToken, 'reason' => 'inappropriate'],
            ])->assertOk();
        }

        $this->postJson("/prayer-community/{$prayer->id}/flags", [
            'data' => ['api_token' => $lastFlaggerToken, 'reason' => 'inappropriate'],
        ])->assertStatus(409);

        $this->assertNotNull($prayer->fresh()->hidden_at);
        $this->getJson('/prayer-community')->assertJsonPath('prayer_requests', []);
    }

    public function test_audio_request_requires_duration_under_sixty_seconds(): void
    {
        Storage::fake('public');
        $user = MobileUser::create(['name' => 'Ada', 'email' => 'audio@example.test', 'password' => 'secret', 'is_verified' => true]);

        $this->post('/prayer-community', [
            'data' => json_encode([
                'api_token' => $user->issueApiToken(),
                'type' => 'audio',
                'audio_duration_seconds' => 61,
            ]),
            'audio' => UploadedFile::fake()->create('request.mp3', 100, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio_duration_seconds');
    }

    public function test_purge_command_deletes_expired_request_and_audio(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('prayer-community/audio/old.mp3', 'audio');
        $user = MobileUser::create(['name' => 'Ada', 'email' => 'old@example.test', 'password' => 'secret', 'is_verified' => true]);
        $prayer = CommunityPrayerRequest::create([
            'mobile_user_id' => $user->id,
            'type' => 'audio',
            'audio_path' => 'prayer-community/audio/old.mp3',
            'audio_duration_seconds' => 30,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('prayer-community:purge-expired --sync')->assertSuccessful();

        $this->assertDatabaseMissing('community_prayer_requests', ['id' => $prayer->id]);
        Storage::disk('public')->assertMissing('prayer-community/audio/old.mp3');
    }

    public function test_go_can_create_and_replace_prophetic_decree(): void
    {
        Storage::fake('public');
        $go = MobileUser::create(['name' => 'General Overseer', 'email' => 'go@example.test', 'password' => 'secret', 'is_verified' => true]);
        Role::firstOrCreate(['name' => 'G.O', 'guard_name' => 'mobile']);
        $go->assignRole('G.O');
        $token = $go->issueApiToken();

        $this->post('/prayer-community/prophetic-decree', [
            'data' => json_encode(['api_token' => $token, 'duration' => 45]),
            'audio' => UploadedFile::fake()->create('first.mp3', 100, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('prophetic_decree.label', 'Prophetic Decree')
            ->assertJsonPath('prophetic_decree.go.name', 'General Overseer');

        $firstPath = PropheticDecree::where('is_active', true)->value('audio_path');

        $this->post('/prayer-community/prophetic-decree', [
            'data' => json_encode(['api_token' => $token, 'duration' => 60]),
            'audio' => UploadedFile::fake()->create('second.mp3', 100, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertDatabaseCount('prophetic_decrees', 2);
        $this->assertSame(1, PropheticDecree::where('is_active', true)->count());
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_can_get_active_prophetic_decree(): void
    {
        $go = MobileUser::create(['name' => 'G.O', 'email' => 'active-go@example.test', 'password' => 'secret', 'is_verified' => true]);
        PropheticDecree::create([
            'go_user_id' => $go->id,
            'audio_path' => 'prayer-community/prophetic-decrees/active.mp3',
            'duration' => 55,
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $this->getJson('/prayer-community/prophetic-decree')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('prophetic_decree.label', 'Prophetic Decree')
            ->assertJsonPath('prophetic_decree.go.name', 'G.O');
    }

    public function test_expired_prophetic_decree_is_not_returned(): void
    {
        $go = MobileUser::create(['name' => 'G.O', 'email' => 'expired-go@example.test', 'password' => 'secret', 'is_verified' => true]);
        PropheticDecree::create([
            'go_user_id' => $go->id,
            'audio_path' => 'prayer-community/prophetic-decrees/expired.mp3',
            'duration' => 55,
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);

        $this->getJson('/prayer-community/prophetic-decree')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('prophetic_decree', null);
    }

    public function test_purge_command_deletes_expired_prophetic_decree_and_audio(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('prayer-community/prophetic-decrees/old.mp3', 'audio');
        $go = MobileUser::create(['name' => 'G.O', 'email' => 'purge-go@example.test', 'password' => 'secret', 'is_verified' => true]);
        $decree = PropheticDecree::create([
            'go_user_id' => $go->id,
            'audio_path' => 'prayer-community/prophetic-decrees/old.mp3',
            'duration' => 55,
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('prayer-community:purge-expired --sync')->assertSuccessful();

        $this->assertDatabaseMissing('prophetic_decrees', ['id' => $decree->id]);
        Storage::disk('public')->assertMissing('prayer-community/prophetic-decrees/old.mp3');
    }

    public function test_non_go_cannot_create_prophetic_decree(): void
    {
        Storage::fake('public');
        $user = MobileUser::create(['name' => 'Ada', 'email' => 'ada2@example.test', 'password' => 'secret', 'is_verified' => true]);

        $this->post('/prayer-community/prophetic-decree', [
            'data' => json_encode(['api_token' => $user->issueApiToken(), 'duration' => 45]),
            'audio' => UploadedFile::fake()->create('decree.mp3', 100, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertDatabaseCount('prophetic_decrees', 0);
    }

    public function test_admin_without_mobile_go_role_cannot_create_prophetic_decree(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['email' => 'admin-no-go@example.test']);

        $this->post('/prayer-community/prophetic-decree', [
            'data' => json_encode(['email' => $admin->email, 'duration' => 45]),
            'audio' => UploadedFile::fake()->create('decree.mp3', 100, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertDatabaseCount('prophetic_decrees', 0);
    }

    public function test_prophetic_decree_requires_valid_audio(): void
    {
        Storage::fake('public');
        $go = MobileUser::create(['name' => 'General Overseer', 'email' => 'go-audio@example.test', 'password' => 'secret', 'is_verified' => true]);
        Role::firstOrCreate(['name' => 'G.O', 'guard_name' => 'mobile']);
        $go->assignRole('G.O');
        $token = $go->issueApiToken();

        $this->post('/prayer-community/prophetic-decree', [
            'data' => json_encode(['api_token' => $token, 'duration' => 45]),
            'audio' => UploadedFile::fake()->create('decree.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');

        $this->post('/prayer-community/prophetic-decree', [
            'data' => json_encode(['api_token' => $token, 'duration' => 601]),
            'audio' => UploadedFile::fake()->create('decree.mp3', 100, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration');
    }

    public function test_prayer_list_includes_prophetic_decree_separately_above_requests(): void
    {
        $go = MobileUser::create(['name' => 'G.O', 'email' => 'go-list@example.test', 'password' => 'secret', 'is_verified' => true, 'avatar' => 'go/avatar.jpg']);
        PropheticDecree::create([
            'go_user_id' => $go->id,
            'audio_path' => 'prayer-community/prophetic-decrees/today.mp3',
            'duration' => 45,
            'is_active' => true,
        ]);
        CommunityPrayerRequest::create([
            'mobile_user_id' => $go->id,
            'type' => 'text',
            'text' => 'Please pray',
            'expires_at' => now()->addDay(),
        ]);

        $this->getJson('/prayer-community')
            ->assertOk()
            ->assertJsonPath('prophetic_decree.label', 'Prophetic Decree')
            ->assertJsonCount(1, 'prayer_requests');
    }
}
