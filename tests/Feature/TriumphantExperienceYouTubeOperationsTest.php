<?php

namespace Tests\Feature;

use App\Enums\GoshenExperienceVideoModerationStatus;
use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Filament\Resources\GoshenExperienceVideoResource;
use App\Filament\Resources\GoshenYouTubeConnectionResource;
use App\Jobs\CheckTriumphantExperienceVideoProcessing;
use App\Jobs\DeleteTriumphantExperienceVideoSource;
use App\Jobs\QueueTriumphantExperienceVideoUpload;
use App\Jobs\ReleaseDeferredTriumphantExperienceUploads;
use App\Jobs\UploadTriumphantExperienceVideoChunk;
use App\Models\GoshenExperienceVideo;
use App\Models\GoshenYouTubeConnection;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenExperienceVideoStateMachine;
use App\Services\YouTube\GoogleYouTubeGateway;
use App\Services\YouTube\YouTubeChannel;
use App\Services\YouTube\YouTubeGateway;
use App\Services\YouTube\YouTubeGatewayException;
use App\Services\YouTube\YouTubeGatewayFailureKind;
use App\Services\YouTube\YouTubeQuotaWindowService;
use App\Services\YouTube\YouTubeUploadChunkResult;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Personal\EventInstallments\Models\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TriumphantExperienceYouTubeOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('goshen_experience.source_disk', 'local');
        config()->set('goshen_experience.source_path', 'goshen/experience/shorts/source');
        config()->set('goshen_experience.youtube.upload_queue', 'youtube-uploads');
        Storage::fake('local');
        Queue::fake();
    }

    public function test_confirmed_youtube_processing_deletes_only_the_expected_private_source_and_stamps_after_success(): void
    {
        $video = $this->video(GoshenExperienceVideoUploadStatus::ReadyForReview, [
            'youtube_video_id' => 'ABCDEFGHIJK',
            'youtube_processed_at' => now(),
        ]);
        Storage::disk('local')->put($video->local_path, 'private source bytes');

        (new DeleteTriumphantExperienceVideoSource($video->id))->handle(app(GoshenExperienceVideoStateMachine::class));

        $video->refresh();
        Storage::disk('local')->assertMissing('goshen/experience/shorts/source/'.$video->uuid.'/source.mp4');
        $this->assertNotNull($video->local_deleted_at);
        $this->assertNull($video->local_path);
    }

    public function test_quota_deferred_source_is_retained_and_daily_quota_does_not_increment_upload_attempts(): void
    {
        $connection = $this->connection();
        $video = $this->video(GoshenExperienceVideoUploadStatus::Queued, [
            'youtube_connection_id' => $connection->id,
        ]);
        Storage::disk('local')->put($video->local_path, 'retained source bytes');

        (new UploadTriumphantExperienceVideoChunk($video->id))->handle(
            new DailyQuotaGateway,
            app(GoshenExperienceVideoStateMachine::class),
            app(YouTubeQuotaWindowService::class),
        );

        $video->refresh();
        $connection->refresh();
        $this->assertSame(GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, $video->upload_status);
        $this->assertSame(0, $video->upload_attempts);
        $this->assertNotNull($video->quota_resume_at);
        $this->assertNotNull($connection->quota_resume_at);
        Storage::disk('local')->assertExists($video->local_path);
    }

    public function test_due_quota_release_queues_exactly_one_oldest_deferred_video_first(): void
    {
        $connection = $this->connection([
            'health' => GoshenYouTubeConnection::HEALTH_QUOTA_BLOCKED,
            'quota_resume_at' => now()->subSecond(),
        ]);
        $oldest = $this->video(GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, [
            'youtube_connection_id' => $connection->id,
            'created_at' => now()->subMinutes(2),
        ]);
        $newest = $this->video(GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, [
            'youtube_connection_id' => $connection->id,
            'created_at' => now()->subMinute(),
        ]);

        (new ReleaseDeferredTriumphantExperienceUploads($connection->id, fromScheduledProbe: true))->handle(
            app(GoshenExperienceVideoStateMachine::class),
            app(YouTubeQuotaWindowService::class),
        );

        $this->assertSame(GoshenExperienceVideoUploadStatus::Queued, $oldest->fresh()->upload_status);
        $this->assertSame(GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, $newest->fresh()->upload_status);
        Queue::assertPushed(QueueTriumphantExperienceVideoUpload::class, fn (QueueTriumphantExperienceVideoUpload $job): bool => $job->videoId === $oldest->id);
        Queue::assertNotPushed(QueueTriumphantExperienceVideoUpload::class, fn (QueueTriumphantExperienceVideoUpload $job): bool => $job->videoId === $newest->id);
    }

    public function test_due_quota_release_resumes_processing_checks_after_the_binary_upload_completed(): void
    {
        $connection = $this->connection([
            'health' => GoshenYouTubeConnection::HEALTH_QUOTA_BLOCKED,
            'quota_resume_at' => now()->subSecond(),
        ]);
        $video = $this->video(GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, [
            'youtube_connection_id' => $connection->id,
            'youtube_video_id' => 'ABCDEFGHIJK',
            'youtube_uploaded_at' => now()->subMinute(),
            'uploaded_bytes' => 1024,
        ]);

        (new ReleaseDeferredTriumphantExperienceUploads($connection->id, fromScheduledProbe: true))->handle(
            app(GoshenExperienceVideoStateMachine::class),
            app(YouTubeQuotaWindowService::class),
        );

        $this->assertSame(GoshenExperienceVideoUploadStatus::YoutubeProcessing, $video->fresh()->upload_status);
        Queue::assertPushed(CheckTriumphantExperienceVideoProcessing::class, fn (CheckTriumphantExperienceVideoProcessing $job): bool => $job->videoId === $video->id);
        Queue::assertNotPushed(QueueTriumphantExperienceVideoUpload::class, fn (QueueTriumphantExperienceVideoUpload $job): bool => $job->videoId === $video->id);
    }

    public function test_processing_quota_deferral_keeps_the_private_source_and_waits_for_the_connection_window(): void
    {
        $connection = $this->connection();
        $video = $this->video(GoshenExperienceVideoUploadStatus::YoutubeProcessing, [
            'youtube_connection_id' => $connection->id,
            'youtube_video_id' => 'ABCDEFGHIJK',
        ]);
        Storage::disk('local')->put($video->local_path, 'retained processing source bytes');

        (new CheckTriumphantExperienceVideoProcessing($video->id))->handle(
            new ProcessingDailyQuotaGateway,
            app(GoshenExperienceVideoStateMachine::class),
            app(YouTubeQuotaWindowService::class),
        );

        $video->refresh();
        $connection->refresh();
        $this->assertSame(GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, $video->upload_status);
        $this->assertNotNull($video->quota_resume_at);
        $this->assertSame($connection->quota_resume_at?->getTimestamp(), $video->quota_resume_at?->getTimestamp());
        Storage::disk('local')->assertExists($video->local_path);
    }

    public function test_gateway_refuses_a_traversal_source_path_before_reading_any_local_file(): void
    {
        $connection = $this->connection();
        $video = $this->video(GoshenExperienceVideoUploadStatus::Uploading, [
            'youtube_connection_id' => $connection->id,
            'local_path' => 'goshen/experience/shorts/source/../outside/source.mp4',
            'resumable_session_url' => 'https://www.googleapis.com/upload/youtube/v3/videos?upload_id=test',
        ]);

        try {
            app(GoogleYouTubeGateway::class)->uploadChunk($video, $connection);
            $this->fail('The gateway attempted to read a traversal path.');
        } catch (YouTubeGatewayException $exception) {
            $this->assertSame('youtube_upload_source_path_invalid', $exception->safeCode);
        }
    }

    public function test_a_later_video_cannot_start_until_the_connection_fifo_head_has_progressed(): void
    {
        $connection = $this->connection();
        $first = $this->video(GoshenExperienceVideoUploadStatus::Queued, [
            'youtube_connection_id' => $connection->id,
            'created_at' => now()->subMinute(),
        ]);
        $second = $this->video(GoshenExperienceVideoUploadStatus::Queued, [
            'youtube_connection_id' => $connection->id,
            'created_at' => now(),
        ]);
        $gateway = new NoUploadStartGateway;

        (new UploadTriumphantExperienceVideoChunk($second->id))->handle(
            $gateway,
            app(GoshenExperienceVideoStateMachine::class),
            app(YouTubeQuotaWindowService::class),
        );

        $this->assertSame(0, $gateway->startCalls);
        $this->assertSame(GoshenExperienceVideoUploadStatus::Queued, $second->fresh()->upload_status);
        Queue::assertPushed(UploadTriumphantExperienceVideoChunk::class, fn (UploadTriumphantExperienceVideoChunk $job): bool => $job->videoId === $second->id);
        $this->assertSame(GoshenExperienceVideoUploadStatus::Queued, $first->fresh()->upload_status);
    }

    public function test_video_moderation_and_channel_connection_have_separate_permissions(): void
    {
        $videoPermission = Permission::findOrCreate(AdminPermissions::resourcePermission(GoshenExperienceVideoResource::class), 'web');
        $youtubePermission = Permission::findOrCreate(AdminPermissions::TRIUMPHANT_EXPERIENCE_YOUTUBE, 'web');
        $role = Role::findOrCreate('triumphant-video-moderator', 'web');
        $role->givePermissionTo($videoPermission);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin);

        $this->assertTrue(GoshenExperienceVideoResource::canViewAny());
        $this->assertFalse(GoshenYouTubeConnectionResource::canViewAny());

        $admin->givePermissionTo($youtubePermission);
        $this->assertTrue(GoshenYouTubeConnectionResource::canViewAny());
    }

    public function test_retention_dry_run_refuses_outside_paths_and_excludes_quota_deferred_sources(): void
    {
        config()->set('goshen_experience.failed_source_retention_days', 30);
        $eligible = $this->video(GoshenExperienceVideoUploadStatus::Failed, ['created_at' => now()->subDays(31)]);
        $outsidePrefix = $this->video(GoshenExperienceVideoUploadStatus::Failed, [
            'local_path' => 'public/not-a-private-source.mp4',
            'created_at' => now()->subDays(31),
        ]);
        $quotaDeferred = $this->video(GoshenExperienceVideoUploadStatus::AwaitingYoutubeQuota, ['created_at' => now()->subDays(31)]);
        $eligible->forceFill(['created_at' => now()->subDays(31)])->saveQuietly();
        $outsidePrefix->forceFill(['created_at' => now()->subDays(31)])->saveQuietly();
        $quotaDeferred->forceFill(['created_at' => now()->subDays(31)])->saveQuietly();
        Storage::disk('local')->put($eligible->local_path, 'eligible');
        Storage::disk('local')->put($outsidePrefix->local_path, 'outside');
        Storage::disk('local')->put($quotaDeferred->local_path, 'quota deferred');

        $this->artisan('goshen:purge-expired-triumphant-experience-sources --dry-run')
            ->assertExitCode(0);

        Storage::disk('local')->assertExists($eligible->local_path);
        Storage::disk('local')->assertExists($outsidePrefix->local_path);
        Storage::disk('local')->assertExists($quotaDeferred->local_path);

        $this->artisan('goshen:purge-expired-triumphant-experience-sources --force')
            ->assertExitCode(0);

        Storage::disk('local')->assertMissing($eligible->local_path);
        Storage::disk('local')->assertExists($outsidePrefix->local_path);
        Storage::disk('local')->assertExists($quotaDeferred->local_path);
    }

    private function connection(array $overrides = []): GoshenYouTubeConnection
    {
        return GoshenYouTubeConnection::query()->create(array_merge([
            'channel_id' => 'UC'.str()->random(22),
            'channel_title' => 'MFM Triumphant Church',
            'scopes' => ['https://www.googleapis.com/auth/youtube.upload'],
            'default_privacy' => 'unlisted',
            'health' => GoshenYouTubeConnection::HEALTH_HEALTHY,
            'refresh_token_payload' => [
                'refresh_token' => 'test-refresh-token',
                'access_token' => 'test-access-token',
                'expires_at' => now()->addHour()->toIso8601String(),
            ],
            'connected_at' => now(),
        ], $overrides));
    }

    private function video(GoshenExperienceVideoUploadStatus $status, array $overrides = []): GoshenExperienceVideo
    {
        $member = MobileUser::query()->create([
            'name' => 'Triumphant attendee '.str()->random(6),
            'email' => str()->random(10).'@example.test',
            'password' => 'secret',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
        $event = Event::query()->create([
            'name' => 'Goshen Retreat',
            'slug' => 'goshen-retreat-'.str()->lower(str()->random(8)),
            'settings' => ['module' => 'goshen_retreat'],
        ]);
        $uuid = (string) str()->uuid();

        return GoshenExperienceVideo::query()->create(array_merge([
            'uuid' => $uuid,
            'event_id' => $event->id,
            'mobile_user_id' => $member->id,
            'client_upload_id' => (string) str()->uuid(),
            'caption' => 'A safeguarded retreat video.',
            'display_name' => 'Ada',
            'is_anonymous' => false,
            'consent_version' => 'triumphant-experience-v1',
            'consented_at' => now(),
            'local_disk' => 'local',
            'local_path' => 'goshen/experience/shorts/source/'.$uuid.'/source.mp4',
            'original_filename' => 'source.mp4',
            'mime_type' => 'video/mp4',
            'file_size_bytes' => 1024,
            'duration_seconds' => 30,
            'width' => 1080,
            'height' => 1920,
            'sha256' => hash('sha256', $uuid),
            'upload_status' => $status,
            'moderation_status' => GoshenExperienceVideoModerationStatus::Pending,
            'uploaded_bytes' => 0,
            'upload_attempts' => 0,
        ], $overrides));
    }
}

class DailyQuotaGateway implements YouTubeGateway
{
    public function currentChannel(string $accessToken): YouTubeChannel
    {
        return new YouTubeChannel('UCtest', 'Test channel');
    }

    public function startResumableUpload(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): string
    {
        throw new YouTubeGatewayException(YouTubeGatewayFailureKind::DailyQuota, 'youtube_http_403');
    }

    public function uploadChunk(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): YouTubeUploadChunkResult
    {
        throw new \LogicException('The quota fake must stop before a chunk is sent.');
    }

    public function processingStatus(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): array
    {
        return ['state' => 'processing', 'thumbnail_url' => null];
    }

    public function deleteVideo(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): void {}
}

class ProcessingDailyQuotaGateway extends DailyQuotaGateway
{
    public function processingStatus(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): array
    {
        throw new YouTubeGatewayException(YouTubeGatewayFailureKind::DailyQuota, 'youtube_http_403');
    }
}

class NoUploadStartGateway implements YouTubeGateway
{
    public int $startCalls = 0;

    public function currentChannel(string $accessToken): YouTubeChannel
    {
        throw new \LogicException('Not used by this test.');
    }

    public function startResumableUpload(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): string
    {
        $this->startCalls++;

        throw new \LogicException('A non-head video must not start a YouTube upload.');
    }

    public function uploadChunk(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): YouTubeUploadChunkResult
    {
        throw new \LogicException('A non-head video must not send a YouTube chunk.');
    }

    public function processingStatus(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): array
    {
        throw new \LogicException('Not used by this test.');
    }

    public function deleteVideo(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): void {}
}
