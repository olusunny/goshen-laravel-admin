<?php

namespace Tests\Feature;

use App\Jobs\DeleteExpiredCommunityPrayerRequests;
use App\Models\CommunityPrayerRequest;
use App\Services\PrayerModerationNotifier;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommunityPrayerRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_mobile_user_can_create_anonymous_text_prayer_request(): void
    {
        $mobileUser = $this->mobileUser();

        $this->actingAs($mobileUser, 'mobile')
            ->postJson('/api/v1/prayer-community/requests', [
                'type' => 'text',
                'text' => 'Please pray for my family.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.identity', 'Anonymous')
            ->assertJsonPath('data.text', 'Please pray for my family.')
            ->assertJsonMissing(['name' => $mobileUser->name])
            ->assertJsonStructure(['data' => ['id', 'expires_at', 'suggestions']]);
    }

    public function test_unverified_mobile_user_cannot_create_prayer_request(): void
    {
        $mobileUser = $this->mobileUser(['is_verified' => false]);

        $this->actingAs($mobileUser, 'mobile')
            ->postJson('/api/v1/prayer-community/requests', [
                'type' => 'text',
                'text' => 'Please pray.',
            ])
            ->assertForbidden();
    }

    public function test_audio_prayer_requests_are_limited_to_sixty_seconds(): void
    {
        Storage::fake('public');
        $mobileUser = $this->mobileUser();

        $this->actingAs($mobileUser, 'mobile')
            ->post('/api/v1/prayer-community/requests', [
                'type' => 'audio',
                'audio_duration_seconds' => 61,
                'audio' => UploadedFile::fake()->create('request.mp3', 128, 'audio/mpeg'),
            ])
            ->assertSessionHasErrors('audio_duration_seconds');

        $this->assertDatabaseCount('community_prayer_requests', 0);
    }

    public function test_comments_and_suggestions_use_anonymous_identity(): void
    {
        $mobileUser = $this->mobileUser();
        $responder = $this->mobileUser(['email' => 'responder@example.com']);
        $request = CommunityPrayerRequest::create([
            'mobile_user_id' => $mobileUser->id,
            'type' => 'text',
            'text' => 'I need wisdom.',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($mobileUser, 'mobile')
            ->getJson("/api/v1/prayer-community/requests/{$request->id}/suggestions")
            ->assertOk()
            ->assertJsonPath('data.0.source', 'preset')
            ->assertJsonPath('data.2.source', 'ai');

        $this->actingAs($responder, 'mobile')
            ->postJson("/api/v1/prayer-community/requests/{$request->id}/comments", [
                'text' => 'I am praying with you.',
                'source' => 'preset',
                'preset_key' => 'praying',
            ])
            ->assertCreated()
            ->assertJsonPath('data.identity', 'Anonymous')
            ->assertJsonPath('data.source', 'preset');

        $this->assertDatabaseHas('community_prayer_requests', [
            'id' => $request->id,
            'comments_count' => 1,
        ]);
    }

    public function test_flags_are_unique_and_auto_hide_at_three_flags(): void
    {
        $notifier = \Mockery::mock(PrayerModerationNotifier::class);
        $notifier->shouldReceive('notifyAutoHidden')->once();
        $this->app->instance(PrayerModerationNotifier::class, $notifier);

        $owner = $this->mobileUser();
        $request = CommunityPrayerRequest::create([
            'mobile_user_id' => $owner->id,
            'type' => 'text',
            'text' => 'A public request.',
            'expires_at' => now()->addDay(),
        ]);

        foreach (range(1, 3) as $number) {
            $flagger = $this->mobileUser(['email' => "flagger{$number}@example.com"]);

            $this->actingAs($flagger, 'mobile')
                ->postJson("/api/v1/prayer-community/requests/{$request->id}/flags", [
                    'reason' => 'inappropriate',
                ])
                ->assertOk();
        }

        $this->assertNotNull($request->fresh()->hidden_at);
        $this->assertSame('auto_hidden_after_flags', $request->fresh()->hidden_reason);

        $this->actingAs($this->mobileUser(['email' => 'late@example.com']), 'mobile')
            ->getJson('/api/v1/prayer-community/requests')
            ->assertOk()
            ->assertJsonMissing(['id' => $request->id]);
    }

    public function test_same_user_cannot_flag_same_prayer_request_twice(): void
    {
        $owner = $this->mobileUser();
        $flagger = $this->mobileUser(['email' => 'flagger@example.com']);
        $request = CommunityPrayerRequest::create([
            'mobile_user_id' => $owner->id,
            'type' => 'text',
            'text' => 'A public request.',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($flagger, 'mobile')
            ->postJson("/api/v1/prayer-community/requests/{$request->id}/flags", ['reason' => 'spam'])
            ->assertOk();

        $this->actingAs($flagger, 'mobile')
            ->postJson("/api/v1/prayer-community/requests/{$request->id}/flags", ['reason' => 'spam'])
            ->assertConflict();
    }

    public function test_expiry_purge_deletes_request_dependents_and_audio(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('prayer-community/audio/request.mp3', 'audio');

        $mobileUser = $this->mobileUser();
        $request = CommunityPrayerRequest::create([
            'mobile_user_id' => $mobileUser->id,
            'type' => 'audio',
            'audio_path' => 'prayer-community/audio/request.mp3',
            'audio_duration_seconds' => 60,
            'expires_at' => now()->subMinute(),
        ]);
        $request->comments()->create([
            'mobile_user_id' => $mobileUser->id,
            'text' => 'Amen.',
        ]);
        $request->flags()->create([
            'mobile_user_id' => $mobileUser->id,
            'reason' => 'spam',
        ]);
        $request->suggestions()->create([
            'source' => 'preset',
            'text' => 'Praying with you.',
        ]);

        app(DeleteExpiredCommunityPrayerRequests::class)->handle();

        $this->assertDatabaseMissing('community_prayer_requests', ['id' => $request->id]);
        $this->assertDatabaseCount('community_prayer_request_comments', 0);
        $this->assertDatabaseCount('community_prayer_request_flags', 0);
        $this->assertDatabaseCount('community_prayer_comment_suggestions', 0);
        Storage::disk('public')->assertMissing('prayer-community/audio/request.mp3');
    }

    public function test_admin_can_hide_restore_and_export_prayer_requests(): void
    {
        $admin = User::factory()->create();
        Role::create(['name' => 'moderator']);
        $admin->assignRole('moderator');

        $request = CommunityPrayerRequest::create([
            'mobile_user_id' => $this->mobileUser()->id,
            'type' => 'text',
            'text' => 'Please moderate this.',
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/prayer-community/requests/{$request->id}/hide", [
                'reason' => 'reviewed',
            ])
            ->assertOk()
            ->assertJsonPath('data.hidden_reason', 'reviewed');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/prayer-community/requests/{$request->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.hidden_reason', null);

        $this->actingAs($admin, 'sanctum')
            ->get('/api/v1/admin/prayer-community/requests/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_mobile_user_can_submit_audio_comment_response(): void
    {
        Storage::fake('public');
        $owner = $this->mobileUser();
        $mobileUser = $this->mobileUser(['email' => 'audio-responder@example.com']);
        $request = CommunityPrayerRequest::create([
            'mobile_user_id' => $owner->id,
            'type' => 'text',
            'text' => 'Pray for my exam.',
            'expires_at' => now()->addDay(),
        ]);

        // 1. Invalid audio duration (> 10 seconds) should fail validation
        $this->actingAs($mobileUser, 'mobile')
            ->postJson("/api/v1/prayer-community/requests/{$request->id}/comments", [
                'type' => 'audio',
                'audio_duration_seconds' => 11,
                'audio' => UploadedFile::fake()->create('comment.mp3', 128, 'audio/mpeg'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio_duration_seconds');

        // 2. Valid audio upload under 10 seconds should succeed
        $audioFile = UploadedFile::fake()->create('comment.mp3', 128, 'audio/mpeg');
        $response = $this->actingAs($mobileUser, 'mobile')
            ->post("/api/v1/prayer-community/requests/{$request->id}/comments", [
                'type' => 'audio',
                'audio_duration_seconds' => 10,
                'audio' => $audioFile,
                'text' => 'God bless you!',
            ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'text',
                    'audio_url',
                    'audio_duration_seconds',
                ]
            ]);

        $this->assertDatabaseHas('community_prayer_request_comments', [
            'community_prayer_request_id' => $request->id,
            'audio_duration_seconds' => 10,
            'text' => 'God bless you!',
        ]);

        $commentId = $response->json('data.id');
        $comment = \App\Models\CommunityPrayerRequestComment::find($commentId);
        $this->assertNotNull($comment->audio_path);
        Storage::disk('public')->assertExists($comment->audio_path);
    }

    public function test_comment_audio_streaming(): void
    {
        Storage::fake('public');
        $mobileUser = $this->mobileUser();
        $request = CommunityPrayerRequest::create([
            'mobile_user_id' => $mobileUser->id,
            'type' => 'text',
            'text' => 'Please pray.',
            'expires_at' => now()->addDay(),
        ]);

        Storage::disk('public')->put('prayer-community/comments/test.mp3', 'dummy audio content');

        $comment = $request->comments()->create([
            'mobile_user_id' => $mobileUser->id,
            'audio_path' => 'prayer-community/comments/test.mp3',
            'audio_duration_seconds' => 5,
            'is_anonymous' => true,
        ]);

        // 1. Can stream audio using the secure URL
        $this->getJson("/api/prayer-community/comments/{$comment->id}/audio")
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg');

        // 2. Cannot stream if comment is hidden
        $comment->update(['hidden_at' => now()]);
        $this->getJson("/api/prayer-community/comments/{$comment->id}/audio")
            ->assertNotFound();
    }

    public function test_comment_audio_cleanup_on_deletion(): void
    {
        Storage::fake('public');
        $mobileUser = $this->mobileUser();
        $request = CommunityPrayerRequest::create([
            'mobile_user_id' => $mobileUser->id,
            'type' => 'text',
            'text' => 'Please pray.',
            'expires_at' => now()->addDay(),
        ]);

        Storage::disk('public')->put('prayer-community/comments/test.mp3', 'dummy audio content');

        $comment = $request->comments()->create([
            'mobile_user_id' => $mobileUser->id,
            'audio_path' => 'prayer-community/comments/test.mp3',
            'audio_duration_seconds' => 5,
            'is_anonymous' => true,
        ]);

        Storage::disk('public')->assertExists('prayer-community/comments/test.mp3');

        // Eloquent model deletion triggers booted deleting hook
        $comment->delete();

        Storage::disk('public')->assertMissing('prayer-community/comments/test.mp3');
    }

    private function mobileUser(array $attributes = []): MobileUser
    {
        return MobileUser::create(array_merge([
            'name' => 'Mobile User',
            'email' => 'mobile'.str()->random(8).'@example.com',
            'password' => 'secret',
            'is_verified' => true,
            'is_blocked' => false,
            'is_deleted' => false,
        ], $attributes));
    }

}
