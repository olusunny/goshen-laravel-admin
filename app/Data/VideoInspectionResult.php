<?php

namespace App\Data;

final readonly class VideoInspectionResult
{
    public function __construct(
        public float $durationSeconds,
        public int $width,
        public int $height,
        public bool $hasVideoStream,
        public bool $hasAudioStream,
        public string $formatName,
        public string $videoCodec,
        public string $audioCodec,
    ) {}

    public function isPortraitWithin(float $minimumRatio, float $maximumRatio): bool
    {
        if ($this->width < 1 || $this->height < 1) {
            return false;
        }

        $ratio = $this->width / $this->height;

        return $ratio >= $minimumRatio && $ratio <= $maximumRatio;
    }
}
