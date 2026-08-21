<?php

namespace App\Services;

use App\Contracts\VideoProbe;
use App\Data\VideoInspectionResult;
use App\Exceptions\VideoInspectionException;
use Illuminate\Validation\ValidationException;

class VideoInspectionService
{
    public function __construct(private readonly VideoProbe $probe = new FfprobeVideoProbe) {}

    public function inspect(string $absolutePath, ?string $sniffedMimeType = null): VideoInspectionResult
    {
        try {
            $inspection = $this->probe->inspect($absolutePath);
        } catch (VideoInspectionException $exception) {
            throw ValidationException::withMessages(['video' => [$exception->getMessage()]]);
        }

        $allowedMimeTypes = config('goshen_experience.allowed_mime_types', ['video/mp4']);
        if (! is_array($allowedMimeTypes) || ! in_array($sniffedMimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages(['video' => ['Upload an MP4 video.']]);
        }

        if (! $inspection->hasVideoStream || ! $inspection->hasAudioStream || $inspection->formatName === '') {
            throw ValidationException::withMessages(['video' => ['The uploaded file must contain valid video and audio streams.']]);
        }

        if (! $this->hasSupportedContainer($inspection->formatName) || blank($inspection->videoCodec) || blank($inspection->audioCodec)) {
            throw ValidationException::withMessages(['video' => ['Upload an MP4 or QuickTime video with readable video and audio codecs.']]);
        }

        $maximumDuration = (float) config('goshen_experience.max_duration_seconds', 60);
        if ($inspection->durationSeconds <= 0 || $inspection->durationSeconds > $maximumDuration) {
            throw ValidationException::withMessages(['video' => ["Videos must be {$maximumDuration} seconds or shorter."]]);
        }

        $minimumRatio = (float) config('goshen_experience.portrait_min_ratio', 0.5);
        $maximumRatio = (float) config('goshen_experience.portrait_max_ratio', 0.65);
        if (! $inspection->isPortraitWithin($minimumRatio, $maximumRatio)) {
            throw ValidationException::withMessages(['video' => ['Record your video in portrait orientation.']]);
        }

        return $inspection;
    }

    private function hasSupportedContainer(string $formatName): bool
    {
        $formats = array_map(
            fn (string $format): string => strtolower(trim($format)),
            explode(',', $formatName),
        );

        return count(array_intersect($formats, ['mp4', 'mov'])) > 0;
    }
}
