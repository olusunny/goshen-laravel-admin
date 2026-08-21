<?php

namespace App\Services\YouTube;

use App\Models\GoshenExperienceVideo;
use App\Models\GoshenYouTubeConnection;

interface YouTubeGateway
{
    public function currentChannel(string $accessToken): YouTubeChannel;

    public function startResumableUpload(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): string;

    public function uploadChunk(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): YouTubeUploadChunkResult;

    /**
     * @return array{state: string, thumbnail_url: string|null}
     */
    public function processingStatus(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): array;

    public function deleteVideo(GoshenExperienceVideo $video, GoshenYouTubeConnection $connection): void;
}
