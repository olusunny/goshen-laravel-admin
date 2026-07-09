<?php

namespace App\Services;

use App\Models\MediaItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class MediaVideoCoverGenerator
{
    public function generateFor(MediaItem $media): ?string
    {
        if ($media->type !== 'video' || filled($media->cover_photo)) {
            return null;
        }

        $youtubeCover = $this->youtubeCover($media);
        if ($youtubeCover) {
            $media->forceFill(['cover_photo' => $youtubeCover])->saveQuietly();

            return $youtubeCover;
        }

        if (($media->source_type ?? null) !== 'upload' || blank($media->source)) {
            return null;
        }

        $sourcePath = Storage::disk('public')->path($media->source);
        if (! is_file($sourcePath) || ! $this->ffmpegAvailable()) {
            return null;
        }

        $directory = 'media/covers/generated';
        Storage::disk('public')->makeDirectory($directory);

        $coverPath = $directory.'/video-'.$media->id.'-'.Str::random(8).'.jpg';
        $targetPath = Storage::disk('public')->path($coverPath);

        try {
            $process = new Process([
                'ffmpeg',
                '-y',
                '-ss',
                '00:00:01',
                '-i',
                $sourcePath,
                '-frames:v',
                '1',
                '-q:v',
                '2',
                $targetPath,
            ]);
            $process->setTimeout(45);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($targetPath) || filesize($targetPath) === 0) {
                Storage::disk('public')->delete($coverPath);

                return null;
            }

            $media->forceFill(['cover_photo' => $coverPath])->saveQuietly();

            return $coverPath;
        } catch (Throwable) {
            Storage::disk('public')->delete($coverPath);

            return null;
        }
    }

    private function youtubeCover(MediaItem $media): ?string
    {
        if (($media->source_type ?? null) !== 'youtube_video' || blank($media->source)) {
            return null;
        }

        $id = $this->youtubeId((string) $media->source);

        return $id ? "https://img.youtube.com/vi/{$id}/hqdefault.jpg" : null;
    }

    private function youtubeId(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z0-9_-]{8,20}$/', $value)) {
            return $value;
        }

        foreach ([
            '/youtu\.be\/([A-Za-z0-9_-]{8,20})/',
            '/youtube\.com\/watch\?v=([A-Za-z0-9_-]{8,20})/',
            '/youtube\.com\/shorts\/([A-Za-z0-9_-]{8,20})/',
            '/youtube\.com\/embed\/([A-Za-z0-9_-]{8,20})/',
        ] as $pattern) {
            if (preg_match($pattern, $value, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function ffmpegAvailable(): bool
    {
        try {
            $process = new Process(['ffmpeg', '-version']);
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }
}
