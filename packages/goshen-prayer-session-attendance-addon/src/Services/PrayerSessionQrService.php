<?php

namespace ChurchTools\GoshenPrayerAttendance\Services;

use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRMarkupSVG;
use Illuminate\Support\Str;
use RuntimeException;

class PrayerSessionQrService
{
    public function activate(PrayerSession $session): string
    {
        $session->forceFill(['qr_generation_id' => Str::random(48)])->save();
        $token = $this->token($session);
        $session->forceFill(['qr_token_hash' => hash('sha256', $token), 'qr_activated_at' => now()])->save();

        return $token;
    }

    public function token(PrayerSession $session): string
    {
        if (! $session->isActive() || blank($session->qr_generation_id)) {
            throw new RuntimeException('This prayer session does not have an active QR code.');
        }

        return 'psa1.'.$session->public_id.'.'.$session->qr_generation_id.'.'.hash_hmac('sha256', $session->public_id.'.'.$session->qr_generation_id, (string) config('app.key'));
    }

    /** @return array{public_id:string, token:string} */
    public function parse(string $token): array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 4 || $parts[0] !== 'psa1') {
            throw new RuntimeException('This is not a valid prayer-session QR code.');
        }

        return ['public_id' => $parts[1], 'token' => $token];
    }

    public function isCurrent(PrayerSession $session, string $token): bool
    {
        try {
            return $session->isActive()
                && filled($session->qr_token_hash)
                && hash_equals((string) $session->qr_token_hash, hash('sha256', $token))
                && hash_equals($this->token($session), $token);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Render an SVG QR code without relying on optional PHP imaging extensions.
     * SVG is supported by modern browsers and Flutter's share/download flow.
     */
    public function renderSvg(PrayerSession $session): string
    {
        if (! class_exists(QRCode::class) || ! class_exists(QRMarkupSVG::class)) {
            throw new RuntimeException('The required chillerlan/php-qrcode package is not installed on this host.');
        }

        return (new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'addQuietzone' => true,
        ])))->render($this->token($session));
    }
}
