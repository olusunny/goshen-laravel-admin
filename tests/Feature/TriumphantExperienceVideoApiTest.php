<?php

namespace Tests\Feature;

use App\Contracts\VideoProbe;
use App\Data\VideoInspectionResult;
use App\Enums\GoshenExperienceVideoModerationStatus;
use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Jobs\QueueTriumphantExperienceVideoUpload;
use App\Models\GoshenExperienceVideo;
use App\Models\MobileUser;
use App\Services\GoshenExperienceVideoStateMachine;
use App\Services\VideoInspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketCheckIn;
use Tests\TestCase;

class TriumphantExperienceVideoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('goshen_experience', [
            'enabled' => true,
            'source_disk' => 'local',
            'source_path' => 'goshen/experience/shorts/source',
            'max_bytes' => 104_857_600,
            'max_duration_seconds' => 60,
            'allowed_mimes' => ['video/mp4'],
            'portrait_ratio_tolerance' => 0.15,
            'release_version' => '2026-08-21',
        ]);

        Storage::fake('local');
        Queue::fake();
        $this->app->instance(VideoInspectionService::class, new VideoInspectionService(new class implements VideoProbe
        {
            public function inspect(string $absolutePath): VideoInspectionResult
            {
                return new VideoInspectionResult(
                    durationSeconds: 59.9,
                    width: 1080,
                    height: 1920,
                    hasVideoStream: true,
                    hasAudioStream: true,
                    formatName: 'mov,mp4,m4a,3gp,3g2,mj2',
                    videoCodec: 'h264',
                    audioCodec: 'aac',
                );
            }
        }));
    }

    public function test_checked_in_attendee_can_submit_once_and_replay_the_same_client_upload_id_without_public_source_data(): void
    {
        [$member, $event] = $this->eligibleMemberAndEvent();
        $token = $member->issueApiToken();
        $clientUploadId = 'd49eeff9-a470-429c-9fbb-4ba606bc2281';

        $payload = [
            'client_upload_id' => $clientUploadId,
            'event_id' => $event->public_id,
            'caption' => 'God was faithful at Goshen.',
            'display_name' => 'Ada',
            'is_anonymous' => false,
            'consent_version' => '2026-08-21',
            'video' => UploadedFile::fake()->create('portrait.mp4', 128, 'video/mp4'),
        ];

        $response = $this->withToken($token)
            ->post('/api/goshen-retreat/experience/triumphant-videos', $payload, ['Accept' => 'application/json']);

        $response
            ->assertAccepted()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('data.upload_status', GoshenExperienceVideoUploadStatus::Received->value)
            ->assertJsonMissingPath('data.local_path')
            ->assertJsonMissingPath('data.local_disk')
            ->assertJsonMissingPath('data.sha256');

        $video = GoshenExperienceVideo::query()->sole();
        $this->assertSame('local', $video->local_disk);
        $this->assertStringStartsWith('goshen/experience/shorts/source/', $video->local_path);
        Storage::disk('local')->assertExists($video->local_path);
        Queue::assertPushed(QueueTriumphantExperienceVideoUpload::class, fn (QueueTriumphantExperienceVideoUpload $job): bool => $job->videoId === $video->id);

        $this->withToken($token)
            ->post('/api/goshen-retreat/experience/triumphant-videos', [
                'client_upload_id' => $clientUploadId,
            ], ['Accept' => 'application/json'])
            ->assertAccepted()
            ->assertJsonPath('data.uuid', $video->uuid);

        $this->assertSame(1, GoshenExperienceVideo::query()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('goshen/experience/shorts/source'));
        Queue::assertPushed(QueueTriumphantExperienceVideoUpload::class, 1);
    }

    public function test_unchecked_in_member_cannot_submit_a_video(): void
    {
        $member = MobileUser::query()->create([
            'name' => 'Unverified attendee',
            'email' => 'not-checked-in@example.test',
            'password' => 'secret',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $event = $this->event();

        $this->withToken($member->issueApiToken())
            ->post('/api/goshen-retreat/experience/triumphant-videos', $this->validPayload($event), ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertDatabaseCount('goshen_experience_videos', 0);
    }

    public function test_guest_and_unverified_members_cannot_submit_a_video(): void
    {
        $event = $this->event();

        $this->post('/api/goshen-retreat/experience/triumphant-videos', $this->validPayload($event), ['Accept' => 'application/json'])
            ->assertUnauthorized();

        $unverified = MobileUser::query()->create([
            'name' => 'Unverified attendee',
            'email' => 'unverified@example.test',
            'password' => 'secret',
            'is_verified' => false,
        ]);

        $this->withToken($unverified->issueApiToken())
            ->post('/api/goshen-retreat/experience/triumphant-videos', $this->validPayload($event), ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertDatabaseCount('goshen_experience_videos', 0);
    }

    public function test_invalid_metadata_and_untrusted_video_mime_are_rejected_before_private_staging(): void
    {
        [$member, $event] = $this->eligibleMemberAndEvent();
        $payload = $this->validPayload($event);
        $payload['consent_version'] = 'obsolete-release';
        $payload['video'] = UploadedFile::fake()->create('not-a-video.txt', 32, 'text/plain');

        $this->withToken($member->issueApiToken())
            ->post('/api/goshen-retreat/experience/triumphant-videos', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consent_version', 'video']);

        Storage::disk('local')->assertDirectoryEmpty('goshen/experience/shorts/source');
    }

    public function test_feed_exposes_only_approved_youtube_safe_data_and_owner_status_does_not_leak_private_source_details(): void
    {
        [$member, $event] = $this->eligibleMemberAndEvent();
        $approved = GoshenExperienceVideo::query()->create([
            'uuid' => 'e60700c8-cc19-424c-86d9-fb3db9f9c07a',
            'event_id' => $event->id,
            'mobile_user_id' => $member->id,
            'client_upload_id' => 'b548f875-ec19-4a29-a5e1-503c6d3fc4d8',
            'caption' => 'Approved testimony.',
            'display_name' => 'Ada',
            'is_anonymous' => false,
            'consent_version' => '2026-08-21',
            'consented_at' => now(),
            'local_disk' => 'local',
            'local_path' => 'goshen/experience/shorts/source/private.mp4',
            'original_filename' => 'private.mp4',
            'mime_type' => 'video/mp4',
            'file_size_bytes' => 100,
            'duration_seconds' => 30,
            'width' => 1080,
            'height' => 1920,
            'sha256' => str_repeat('a', 64),
            'youtube_video_id' => 'safe-youtube-id',
            'youtube_url' => 'https://www.youtube.com/watch?v=safe-youtube-id',
            'youtube_thumbnail_url' => 'https://i.ytimg.com/vi/safe-youtube-id/hqdefault.jpg',
            'youtube_privacy_status' => 'unlisted',
            'youtube_processed_at' => now(),
            'upload_status' => GoshenExperienceVideoUploadStatus::ReadyForReview,
            'moderation_status' => GoshenExperienceVideoModerationStatus::Approved,
            'approved_at' => now(),
            'last_error_message' => 'must never leave the server',
        ]);

        $this->getJson('/api/goshen-retreat/experience/triumphant-videos')
            ->assertOk()
            ->assertJsonPath('feature.enabled', true)
            ->assertJsonPath('feature.consent_version', '2026-08-21')
            ->assertJsonPath('data.0.uuid', $approved->uuid)
            ->assertJsonPath('data.0.youtube_video_id', 'safe-youtube-id')
            ->assertJsonMissingPath('feature.local_path')
            ->assertJsonMissingPath('feature.refresh_token_payload')
            ->assertJsonMissingPath('data.0.local_path')
            ->assertJsonMissingPath('data.0.local_disk')
            ->assertJsonMissingPath('data.0.sha256')
            ->assertJsonMissingPath('data.0.last_error_message');

        $this->withToken($member->issueApiToken())
            ->getJson("/api/goshen-retreat/experience/triumphant-videos/{$approved->uuid}")
            ->assertOk()
            ->assertJsonMissingPath('data.local_path')
            ->assertJsonMissingPath('data.last_error_message');
    }

    public function test_owner_status_hides_youtube_playback_identifiers_until_moderation_approval(): void
    {
        [$member, $event] = $this->eligibleMemberAndEvent();
        $pending = GoshenExperienceVideo::factory()->create([
            'event_id' => $event->id,
            'mobile_user_id' => $member->id,
            'upload_status' => GoshenExperienceVideoUploadStatus::YoutubeProcessing,
            'moderation_status' => GoshenExperienceVideoModerationStatus::Pending,
            'youtube_video_id' => 'not-yet-approved',
            'youtube_thumbnail_url' => 'https://i.ytimg.com/vi/not-yet-approved/hqdefault.jpg',
            'youtube_privacy_status' => 'unlisted',
        ]);

        $this->withToken($member->issueApiToken())
            ->getJson("/api/goshen-retreat/experience/triumphant-videos/{$pending->uuid}")
            ->assertOk()
            ->assertJsonMissingPath('data.youtube_video_id')
            ->assertJsonMissingPath('data.youtube_url')
            ->assertJsonMissingPath('data.youtube_thumbnail_url')
            ->assertJsonMissingPath('data.youtube_privacy_status');
    }

    public function test_database_invariant_allows_only_one_active_submission_per_member_and_event(): void
    {
        [$member, $event] = $this->eligibleMemberAndEvent();
        GoshenExperienceVideo::factory()->create([
            'event_id' => $event->id,
            'mobile_user_id' => $member->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        GoshenExperienceVideo::factory()->create([
            'event_id' => $event->id,
            'mobile_user_id' => $member->id,
        ]);
    }

    public function test_cancelled_submission_persists_a_null_active_key_so_a_new_submission_can_be_created(): void
    {
        [$member, $event] = $this->eligibleMemberAndEvent();
        $cancelled = GoshenExperienceVideo::factory()->create([
            'event_id' => $event->id,
            'mobile_user_id' => $member->id,
        ]);

        app(GoshenExperienceVideoStateMachine::class)
            ->transition($cancelled, GoshenExperienceVideoUploadStatus::Cancelled);
        $cancelled->save();

        GoshenExperienceVideo::factory()->create([
            'event_id' => $event->id,
            'mobile_user_id' => $member->id,
        ]);

        $this->assertDatabaseHas('goshen_experience_videos', [
            'id' => $cancelled->id,
            'active_submission_key' => null,
        ]);
        $this->assertSame(2, GoshenExperienceVideo::query()->count());
    }

    /** @return array{MobileUser, Event} */
    private function eligibleMemberAndEvent(): array
    {
        $member = MobileUser::query()->create([
            'name' => 'Ada Attendee',
            'email' => 'ada@example.test',
            'password' => 'secret',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $event = $this->event();
        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'currency' => 'GBP',
            'status' => BookingStatus::Paid,
        ]);
        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Attendee',
            'currency' => 'GBP',
            'price' => 100,
        ]);
        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'T-'.str()->random(8),
            'qr_hash' => hash('sha256', str()->random(32)),
            'status' => TicketStatus::CheckedIn,
        ]);
        TicketCheckIn::query()->create([
            'ticket_id' => $ticket->id,
            'event_id' => $event->id,
            'status' => TicketStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);

        return [$member, $event];
    }

    private function event(): Event
    {
        return Event::query()->create([
            'name' => 'Goshen Retreat August',
            'slug' => 'goshen-retreat-'.str()->lower(str()->random(8)),
            'settings' => ['module' => 'goshen_retreat'],
        ]);
    }

    private function validPayload(Event $event): array
    {
        return [
            'client_upload_id' => (string) str()->uuid(),
            'event_id' => $event->public_id,
            'caption' => 'A short, moderated experience.',
            'display_name' => 'Ada',
            'is_anonymous' => false,
            'consent_version' => '2026-08-21',
            'video' => UploadedFile::fake()->create('portrait.mp4', 128, 'video/mp4'),
        ];
    }
}
