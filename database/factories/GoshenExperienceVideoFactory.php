<?php

namespace Database\Factories;

use App\Enums\GoshenExperienceVideoModerationStatus;
use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Models\GoshenExperienceVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoshenExperienceVideo> */
class GoshenExperienceVideoFactory extends Factory
{
    protected $model = GoshenExperienceVideo::class;

    public function definition(): array
    {
        $uuid = $this->faker->uuid();

        return [
            'uuid' => $uuid,
            'client_upload_id' => $this->faker->uuid(),
            'caption' => $this->faker->sentence(),
            'display_name' => $this->faker->firstName(),
            'is_anonymous' => false,
            'consent_version' => 'triumphant-experience-v1',
            'consented_at' => now(),
            'local_disk' => 'local',
            'local_path' => "goshen/experience/shorts/source/{$uuid}/source.mp4",
            'original_filename' => 'source.mp4',
            'mime_type' => 'video/mp4',
            'file_size_bytes' => 1024,
            'duration_seconds' => 30,
            'width' => 1080,
            'height' => 1920,
            'sha256' => hash('sha256', $uuid),
            'upload_status' => GoshenExperienceVideoUploadStatus::Received,
            'moderation_status' => GoshenExperienceVideoModerationStatus::Pending,
            'uploaded_bytes' => 0,
            'upload_attempts' => 0,
        ];
    }
}
