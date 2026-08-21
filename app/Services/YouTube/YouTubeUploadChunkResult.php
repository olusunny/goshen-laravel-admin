<?php

namespace App\Services\YouTube;

final readonly class YouTubeUploadChunkResult
{
    public function __construct(
        public int $uploadedBytes,
        public ?string $youtubeVideoId = null,
    ) {}

    public function complete(): bool
    {
        return filled($this->youtubeVideoId);
    }
}
