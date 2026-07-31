<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Services;

use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCelebration;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayPreference;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BirthdayCardService
{
    private const VARIANTS = [
        'square' => [1080, 1080],
        'portrait' => [1080, 1350],
    ];

    public function generate(BirthdayCelebration $celebration, Model $member, ?BirthdayPreference $preference, ?BirthdayTemplate $template): BirthdayCelebration
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
            throw new RuntimeException('The GD PNG extension is required for birthday cards.');
        }

        $this->delete($celebration);
        $name = trim((string) ($preference?->preferred_name ?: $member->name ?: 'Church Member'));
        $background = $this->colour($template?->background_color, '#4A2E62');
        $accent = $this->colour($template?->accent_color, '#D49A2A');
        $verse = trim((string) ($preference?->preferredVerse?->is_active ? $preference->preferredVerse->body : ($template?->verse ?: 'May the Lord bless you and keep you.')));
        $photo = $preference?->use_profile_photo ? $this->photo($member) : null;
        $disk = (string) config('church-birthday-celebrations.media.disk', 'local');
        $directory = trim((string) config('church-birthday-celebrations.media.path', 'church-birthday-celebrations/cards'), '/');
        $paths = [];

        try {
            foreach (self::VARIANTS as $variant => [$width, $height]) {
                $path = "{$directory}/{$celebration->public_id}-{$variant}.png";
                Storage::disk($disk)->put($path, $this->render($width, $height, $name, $verse, $background, $accent, $photo));
                $paths[$variant] = $path;
            }
        } finally {
            if ($photo) {
                imagedestroy($photo);
            }
        }

        $metadata = is_array($celebration->metadata) ? $celebration->metadata : [];
        $metadata['card_variants'] = $paths;
        $metadata['template_version'] = $template?->version;

        $celebration->forceFill([
            'template_id' => $template?->id,
            'card_disk' => $disk,
            'card_path' => $paths['portrait'],
            'card_mime' => 'image/png',
            'display_name' => $name,
            'verse' => $verse,
            'metadata' => $metadata,
        ])->save();

        return $celebration;
    }

    public function pathFor(BirthdayCelebration $celebration, string $variant): ?string
    {
        $variants = data_get($celebration->metadata, 'card_variants', []);

        return is_array($variants) ? ($variants[$variant] ?? null) : null;
    }

    public function delete(BirthdayCelebration $celebration): void
    {
        $paths = array_filter([
            $celebration->card_path,
            $this->pathFor($celebration, 'square'),
            $this->pathFor($celebration, 'portrait'),
        ]);

        if ($paths !== []) {
            Storage::disk($celebration->card_disk ?: config('church-birthday-celebrations.media.disk'))->delete(array_values(array_unique($paths)));
        }
    }

    private function render(int $width, int $height, string $name, string $verse, string $background, string $accent, mixed $photo): string
    {
        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);
        imagefill($image, 0, 0, $this->allocate($image, $background));
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 36, 31, 42);
        $muted = imagecolorallocate($image, 91, 82, 98);
        $accentColour = $this->allocate($image, $accent);
        $margin = (int) round($width * 0.065);
        imagefilledrectangle($image, $margin, $margin, $width - $margin, $height - $margin, $white);
        imagefilledrectangle($image, $margin, $margin, $width - $margin, $margin + 18, $accentColour);

        $photoSize = (int) round($width * 0.25);
        $photoX = (int) (($width - $photoSize) / 2);
        $photoY = (int) round($height * 0.12);
        if ($photo) {
            $sourceWidth = imagesx($photo);
            $sourceHeight = imagesy($photo);
            $crop = min($sourceWidth, $sourceHeight);
            imagecopyresampled(
                $image,
                $photo,
                $photoX,
                $photoY,
                (int) (($sourceWidth - $crop) / 2),
                (int) (($sourceHeight - $crop) / 2),
                $photoSize,
                $photoSize,
                $crop,
                $crop,
            );
        } else {
            imagefilledellipse($image, (int) ($width / 2), $photoY + (int) ($photoSize / 2), $photoSize, $photoSize, $accentColour);
            $this->text($image, strtoupper(mb_substr($name, 0, 1)), (int) round($photoSize * 0.34), $width / 2, $photoY + $photoSize * 0.63, $white, true);
        }

        $this->text($image, 'MFM TRIUMPHANT CHURCH', 28, $width / 2, $photoY + $photoSize + 72, $muted, true);
        $this->text($image, 'Happy Birthday', 58, $width / 2, $photoY + $photoSize + 165, $this->allocate($image, $background), true, true);
        $this->text($image, $name, 44, $width / 2, $photoY + $photoSize + 235, $ink, true, true);
        imagefilledrectangle($image, (int) ($width * 0.34), $photoY + $photoSize + 285, (int) ($width * 0.66), $photoY + $photoSize + 294, $accentColour);
        $this->wrappedText($image, $verse, 27, $width / 2, $photoY + $photoSize + 365, (int) ($width * 0.72), $ink);
        $this->text($image, 'Celebrating with your church family', 25, $width / 2, $height - $margin - 70, $muted, true);

        ob_start();
        imagepng($image, null, 8);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private function photo(Model $member): mixed
    {
        $path = trim((string) ($member->avatar ?? ''));
        if ($path === '' || str_contains($path, '://') || str_contains($path, '..')) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path) || $disk->size($path) > (int) config('church-birthday-celebrations.media.max_bytes', 5 * 1024 * 1024)) {
                return null;
            }
            $bytes = $disk->get($path);
            $size = @getimagesizefromstring($bytes);
            if (! $size || ! in_array($size['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                return null;
            }
            $width = (int) ($size[0] ?? 0);
            $height = (int) ($size[1] ?? 0);
            if ($width < 1 || $height < 1
                || $width > (int) config('church-birthday-celebrations.media.max_width', 4096)
                || $height > (int) config('church-birthday-celebrations.media.max_height', 4096)
                || $width * $height > (int) config('church-birthday-celebrations.media.max_pixels', 16_000_000)) {
                return null;
            }

            return @imagecreatefromstring($bytes) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function wrappedText(mixed $image, string $value, int $size, float $x, float $y, int $maximumWidth, int $colour): void
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($value)) ?: [] as $word) {
            $candidate = trim($line.' '.$word);
            if ($line !== '' && $this->textWidth($candidate, $size) > $maximumWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        foreach (array_slice($lines, 0, 4) as $index => $text) {
            $this->text($image, $text, $size, $x, $y + ($index * ($size + 16)), $colour, true);
        }
    }

    private function text(mixed $image, string $value, int $size, float $x, float $y, int $colour, bool $centred = false, bool $bold = false): void
    {
        $font = $this->font($bold);
        if ($font && function_exists('imagettftext')) {
            $box = imagettfbbox($size, 0, $font, $value);
            $drawX = $centred ? (int) ($x - (($box[2] - $box[0]) / 2)) : (int) $x;
            imagettftext($image, $size, 0, $drawX, (int) $y, $colour, $font, $value);

            return;
        }

        imagestring($image, 5, (int) ($centred ? $x - (strlen($value) * 4.5) : $x), (int) $y, $value, $colour);
    }

    private function textWidth(string $value, int $size): int
    {
        $font = $this->font();
        if ($font && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $font, $value);

            return $box[2] - $box[0];
        }

        return strlen($value) * 9;
    }

    private function font(bool $bold = false): ?string
    {
        $path = base_path('vendor/dompdf/dompdf/lib/fonts/'.($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));

        return is_file($path) ? $path : null;
    }

    private function allocate(mixed $image, string $hex): int
    {
        return imagecolorallocate($image, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
    }

    private function colour(?string $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $fallback;
    }
}
