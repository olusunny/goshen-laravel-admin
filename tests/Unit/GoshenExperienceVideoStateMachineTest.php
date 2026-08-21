<?php

namespace Tests\Unit;

use App\Enums\GoshenExperienceVideoModerationStatus;
use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Models\GoshenExperienceVideo;
use App\Services\GoshenExperienceVideoStateMachine;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class GoshenExperienceVideoStateMachineTest extends TestCase
{
    public function test_it_allows_the_delivery_lifecycle_without_publishing_the_video(): void
    {
        $video = new GoshenExperienceVideo([
            'upload_status' => GoshenExperienceVideoUploadStatus::Received,
            'moderation_status' => GoshenExperienceVideoModerationStatus::Pending,
        ]);

        $stateMachine = app(GoshenExperienceVideoStateMachine::class);

        $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::Queued);
        $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::Uploading);
        $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::YoutubeProcessing);
        $stateMachine->transition($video, GoshenExperienceVideoUploadStatus::ReadyForReview);

        $this->assertSame(GoshenExperienceVideoUploadStatus::ReadyForReview, $video->upload_status);
        $this->assertSame(GoshenExperienceVideoModerationStatus::Pending, $video->moderation_status);
    }

    public function test_it_rejects_illegal_upload_status_transitions(): void
    {
        $video = new GoshenExperienceVideo([
            'upload_status' => GoshenExperienceVideoUploadStatus::Received,
            'moderation_status' => GoshenExperienceVideoModerationStatus::Pending,
        ]);

        $this->expectException(\DomainException::class);

        app(GoshenExperienceVideoStateMachine::class)
            ->transition($video, GoshenExperienceVideoUploadStatus::ReadyForReview);
    }

    public function test_cancelling_a_submission_releases_only_its_active_event_key(): void
    {
        $video = new GoshenExperienceVideo([
            'upload_status' => GoshenExperienceVideoUploadStatus::Received,
            'moderation_status' => GoshenExperienceVideoModerationStatus::Pending,
            'active_submission_key' => 'active',
        ]);

        app(GoshenExperienceVideoStateMachine::class)
            ->transition($video, GoshenExperienceVideoUploadStatus::Cancelled);

        $this->assertNull($video->active_submission_key);
    }

    public function test_it_only_allows_local_deletion_after_verified_youtube_processing_on_the_private_source_prefix(): void
    {
        config()->set('goshen_experience.source_disk', 'local');
        config()->set('goshen_experience.source_path', 'goshen/experience/shorts/source');

        $video = new GoshenExperienceVideo([
            'upload_status' => GoshenExperienceVideoUploadStatus::ReadyForReview,
            'youtube_video_id' => 'verified-youtube-id',
            'youtube_processed_at' => CarbonImmutable::now(),
            'local_disk' => 'local',
            'local_path' => 'goshen/experience/shorts/source/a/video.mp4',
        ]);

        $stateMachine = app(GoshenExperienceVideoStateMachine::class);

        $this->assertTrue($stateMachine->canDeleteLocalSource($video));

        $video->local_path = 'public/untrusted.mp4';
        $this->assertFalse($stateMachine->canDeleteLocalSource($video));

        $video->local_path = 'goshen/experience/shorts/source/a/../outside.mp4';
        $this->assertFalse($stateMachine->canDeleteLocalSource($video));

        $video->local_path = 'goshen\\experience\\shorts\\source\\a\\..\\outside.mp4';
        $this->assertFalse($stateMachine->canDeleteLocalSource($video));
    }
}
