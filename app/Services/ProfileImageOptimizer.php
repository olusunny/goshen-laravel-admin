<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileImageOptimizer
{
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        if (! $this->canConvertToWebp($file)) {
            return $file->store($directory, $disk);
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $source) {
            return $file->store($directory, $disk);
        }

        $source = $this->rotateJpegFromExif($source, $file);
        $width = imagesx($source);
        $height = imagesy($source);
        [$targetWidth, $targetHeight] = $this->targetSize($width, $height);

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $stream = fopen('php://temp', 'w+b');
        $converted = $stream !== false && imagewebp($target, $stream, 82);

        imagedestroy($source);
        imagedestroy($target);

        if (! $converted || $stream === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            return $file->store($directory, $disk);
        }

        rewind($stream);
        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        Storage::disk($disk)->put($path, stream_get_contents($stream) ?: '', ['visibility' => 'public']);
        fclose($stream);

        return $path;
    }

    private function canConvertToWebp(UploadedFile $file): bool
    {
        return function_exists('imagecreatefromstring')
            && function_exists('imagewebp')
            && is_file($file->getRealPath());
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function targetSize(int $width, int $height): array
    {
        $maxDimension = 1200;

        if ($width <= $maxDimension && $height <= $maxDimension) {
            return [$width, $height];
        }

        $ratio = min($maxDimension / max(1, $width), $maxDimension / max(1, $height));

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    /**
     * @param resource|\GdImage $image
     * @return resource|\GdImage
     */
    private function rotateJpegFromExif($image, UploadedFile $file)
    {
        if (! function_exists('exif_read_data') || ! str_contains((string) $file->getMimeType(), 'jpeg')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $orientation = (int) ($exif['Orientation'] ?? 0);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }
}
