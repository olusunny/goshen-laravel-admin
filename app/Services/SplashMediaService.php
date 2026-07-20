<?php

namespace App\Services;

use App\Models\SplashMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SplashMediaService
{
    public function active(): ?SplashMedia
    {
        return SplashMedia::query()
            ->active()
            ->latest('activated_at')
            ->latest('version')
            ->first();
    }

    public function publicPayload(): array
    {
        $media = $this->active();

        if (! $media || ! $media->enabled) {
            return [
                'enabled' => false,
                'media_type' => $media?->media_type,
                'version' => $media?->version,
                'checksum' => $media?->checksum,
                'media_url' => null,
                'thumbnail_url' => null,
                'duration_ms' => $media?->duration_ms,
                'updated_at' => $media?->updated_at?->toJSON(),
            ];
        }

        return [
            'enabled' => true,
            'media_type' => $media->media_type,
            'version' => $media->version,
            'checksum' => $media->checksum,
            'media_url' => $media->media_url,
            'thumbnail_url' => $media->thumbnail_url,
            'duration_ms' => $media->duration_ms,
            'updated_at' => $media->updated_at?->toJSON(),
        ];
    }

    public function activate(SplashMedia $media, ?User $user = null): SplashMedia
    {
        return DB::transaction(function () use ($media, $user): SplashMedia {
            SplashMedia::query()->lockForUpdate()->get(['id']);

            SplashMedia::query()
                ->whereKeyNot($media->getKey())
                ->update(['active' => false]);

            $media->forceFill([
                'active' => true,
                'enabled' => true,
                'activated_by_id' => $user?->getKey(),
                'activated_at' => now(),
            ])->save();

            return $media->refresh();
        });
    }

    public function revertToPrevious(?User $user = null): ?SplashMedia
    {
        $current = $this->active();

        if (! $current) {
            return null;
        }

        $previous = SplashMedia::query()
            ->whereKeyNot($current->getKey())
            ->orderByDesc('version')
            ->first();

        return $previous ? $this->activate($previous, $user) : null;
    }

    public function refreshMetadata(SplashMedia $media): SplashMedia
    {
        $disk = Storage::disk('public');
        $path = $media->media_path;

        if (blank($path) || ! $disk->exists($path)) {
            return $media;
        }

        $absolutePath = $disk->path($path);
        $mimeType = $disk->mimeType($path);
        $size = $disk->size($path);
        $dimensions = $this->imageDimensions($absolutePath);

        $media->forceFill([
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'checksum' => hash_file('sha256', $absolutePath) ?: null,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ])->save();

        return $media->refresh();
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function imageDimensions(string $absolutePath): array
    {
        $size = @getimagesize($absolutePath);

        if ($size === false) {
            return ['width' => null, 'height' => null];
        }

        return [
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
        ];
    }
}
