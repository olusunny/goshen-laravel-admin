<?php

namespace App\Services;

use App\Contracts\VideoProbe;
use App\Data\VideoInspectionResult;
use App\Exceptions\VideoInspectionException;
use Illuminate\Support\Facades\Process;
use Throwable;

class FfprobeVideoProbe implements VideoProbe
{
    public function inspect(string $absolutePath): VideoInspectionResult
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new VideoInspectionException('The uploaded video could not be inspected.');
        }

        $binary = (string) config('goshen_experience.ffprobe_binary', 'ffprobe');
        if (trim($binary) === '') {
            throw new VideoInspectionException('Video inspection is unavailable.');
        }

        try {
            $result = Process::timeout(15)->run([
                $binary,
                '-v', 'error',
                '-show_entries', 'format=duration,format_name:stream=codec_type,codec_name,width,height',
                '-of', 'json',
                $absolutePath,
            ]);
        } catch (Throwable) {
            throw new VideoInspectionException('Video inspection is unavailable.');
        }

        if (! $result->successful()) {
            throw new VideoInspectionException('The uploaded video could not be inspected.');
        }

        try {
            $payload = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new VideoInspectionException('The uploaded video could not be inspected.');
        }

        $streams = is_array($payload['streams'] ?? null) ? $payload['streams'] : [];
        $videoStream = collect($streams)->first(fn (mixed $stream): bool => is_array($stream) && ($stream['codec_type'] ?? null) === 'video');
        $audioStream = collect($streams)->first(fn (mixed $stream): bool => is_array($stream) && ($stream['codec_type'] ?? null) === 'audio');
        $format = is_array($payload['format'] ?? null) ? $payload['format'] : [];

        return new VideoInspectionResult(
            durationSeconds: (float) ($format['duration'] ?? 0),
            width: (int) ($videoStream['width'] ?? 0),
            height: (int) ($videoStream['height'] ?? 0),
            hasVideoStream: is_array($videoStream),
            hasAudioStream: is_array($audioStream),
            formatName: (string) ($format['format_name'] ?? ''),
            videoCodec: (string) ($videoStream['codec_name'] ?? ''),
            audioCodec: (string) ($audioStream['codec_name'] ?? ''),
        );
    }
}
