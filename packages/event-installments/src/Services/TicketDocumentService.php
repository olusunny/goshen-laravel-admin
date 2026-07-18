<?php

namespace Personal\EventInstallments\Services;

use App\Models\MobileUser;
use App\Services\GoshenTicketPdfTemplateSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketDocument;
use RuntimeException;

class TicketDocumentService
{
    public function __construct(private readonly QrPayloadService $qrPayload) {}

    public function generateQr(Ticket $ticket): TicketDocument
    {
        if (! class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            throw new RuntimeException('Install simplesoftwareio/simple-qrcode to generate QR images.');
        }

        $disk = config('event-installments.storage.disk');
        $path = trim(config('event-installments.storage.qr_path'), '/').'/'.$ticket->public_id.'.png';
        $png = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(512)
            ->margin(1)
            ->generate($this->qrPayload->encodedPayloadFor($ticket));

        Storage::disk($disk)->put($path, $png);

        return TicketDocument::query()->updateOrCreate(
            ['ticket_id' => $ticket->id, 'type' => 'qr'],
            [
                'disk' => $disk,
                'path' => $path,
                'mime_type' => 'image/png',
                'generated_at' => now(),
            ],
        );
    }

    public function generatePdf(Ticket $ticket): TicketDocument
    {
        $ticket->loadMissing(['event', 'booking', 'attendee', 'ticketType']);
        $disk = config('event-installments.storage.disk');
        $path = trim(config('event-installments.storage.pdf_path'), '/').'/'.$ticket->public_id.'.pdf';

        $pdf = extension_loaded('gd') && class_exists(Dompdf::class)
            ? $this->dompdfTicketPdf($ticket)
            : (extension_loaded('gd') && class_exists(Pdf::class)
                ? Pdf::loadHTML($this->ticketHtml($ticket))->output()
                : $this->basicTicketPdf($ticket));

        Storage::disk($disk)->put($path, $pdf);

        return TicketDocument::query()->updateOrCreate(
            ['ticket_id' => $ticket->id, 'type' => 'pdf'],
            [
                'disk' => $disk,
                'path' => $path,
                'mime_type' => 'application/pdf',
                'generated_at' => now(),
            ],
        );
    }

    public function generateIcs(Ticket $ticket): TicketDocument
    {
        $ticket->loadMissing(['event', 'attendee']);
        $schedule = $ticket->event->schedules()->orderBy('day_number')->first();

        if (! $schedule) {
            throw new RuntimeException('Ticket event has no schedule.');
        }

        $disk = config('event-installments.storage.disk');
        $path = trim(config('event-installments.storage.ics_path'), '/').'/'.$ticket->public_id.'.ics';
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Personal Event Installments//EN',
            'BEGIN:VEVENT',
            'UID:'.$ticket->public_id,
            'SUMMARY:'.$this->escapeIcs($ticket->event->name),
            'DTSTART:'.$schedule->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.($schedule->ends_at ?: $schedule->starts_at)->utc()->format('Ymd\THis\Z'),
            'LOCATION:'.$this->escapeIcs((string) $ticket->event->venue_name),
            'DESCRIPTION:'.$this->escapeIcs('Ticket '.($ticket->formatted_number ?: $ticket->ticket_number)),
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        Storage::disk($disk)->put($path, $ics);

        return TicketDocument::query()->updateOrCreate(
            ['ticket_id' => $ticket->id, 'type' => 'ics'],
            [
                'disk' => $disk,
                'path' => $path,
                'mime_type' => 'text/calendar',
                'generated_at' => now(),
            ],
        );
    }

    private function ticketHtml(Ticket $ticket): string
    {
        $attendee = trim(($ticket->attendee?->first_name ?? '').' '.($ticket->attendee?->last_name ?? ''));
        $number = $ticket->formatted_number ?: $ticket->ticket_number;
        $amountPaid = $this->amountPaidLabel($ticket);
        $status = $ticket->status instanceof \BackedEnum
            ? $ticket->status->value
            : (string) $ticket->status;
        $eventTitle = $ticket->event?->name ?: 'Goshen Retreat';
        $ticketType = $ticket->ticketType?->name ?: 'Ticket';
        $issuedAt = $ticket->issued_at?->format('M j, Y g:i A') ?: 'Not recorded';
        $logo = $this->imageDataUri(public_path('images/goshenretreatlogo.png'), 'image/png');
        $qr = $this->qrDataUri($ticket);
        $profile = $this->profileImageDataUri($ticket);
        $venueName = trim((string) $ticket->event?->venue_name);
        $venueAddress = trim((string) $ticket->event?->venue_address);
        $venueName = $venueName !== '' ? $venueName : 'Venue details will be shared by the church.';
        $template = app(GoshenTicketPdfTemplateSettings::class)->active();
        $profileImage = $profile !== ''
            ? '<img class="avatar" src="'.$profile.'" alt="Attendee profile image">'
            : '';

        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 16px; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; background: #ffffff; }
        .ticket { width: 100%; height: 1080px; overflow: hidden; page-break-inside: avoid; page-break-after: avoid; background: #ffffff; }
        .logo { object-fit: contain; }
        .label { display: block; text-transform: uppercase; letter-spacing: .14em; font-size: 11px; font-weight: 800; color: #9b7500; }
        .value { display: block; margin-top: 8px; font-size: 18px; line-height: 1.3; color: #111827; font-weight: 800; }
        .muted { color: #5b6878; }
        .status { display: inline-block; padding: 9px 15px; border-radius: 999px; background: #e8f8ef; color: #047857; font-size: 14px; font-weight: 800; }
        .avatar { width: 82px; height: 82px; border-radius: 999px; object-fit: cover; border: 6px solid #ffffff; }
        .qr { object-fit: contain; image-rendering: pixelated; }
        .qr-box { background: #ffffff; border: 3px solid #08283a; text-align: center; }
        .card { border: 1px solid #dbe3ea; border-radius: 18px; background: #f8fafc; padding: 22px; }
        .venue-light { border: 1px solid #f0c94d; border-radius: 18px; background: #fff8df; padding: 20px 24px; }
        .venue-dark { border-radius: 18px; background: #08283a; color: #ffffff; padding: 22px 24px; }
        .venue-dark .label { color: #f1c431; }
        .venue-dark .value { color: #ffffff; }
        .footer-note { color: #526170; font-size: 13px; line-height: 1.55; }
        .line { height: 1px; background: #dbe3ea; }
        .title-serif { font-family: DejaVu Serif, serif; }

        .executive_white { border: 3px solid #d9a920; border-radius: 26px; padding: 28px 34px; }
        .executive_white .header { text-align: center; padding-bottom: 24px; border-bottom: 1px solid #dbe3ea; }
        .executive_white .header .logo { width: 250px; height: 84px; }
        .executive_white h1 { margin: 8px 0 4px; font-size: 32px; line-height: 1.15; color: #08283a; }
        .executive_white .subtitle { color: #5b6878; font-size: 18px; font-weight: 800; }
        .executive_white .main { width: 100%; margin-top: 34px; border-collapse: collapse; }
        .executive_white .qr-cell { width: 52%; text-align: center; vertical-align: top; padding-right: 32px; }
        .executive_white .info-cell { width: 48%; vertical-align: top; padding-left: 32px; border-left: 1px solid #dbe3ea; }
        .executive_white .qr-box { border-radius: 18px; padding: 24px; }
        .executive_white .qr { width: 295px; height: 295px; }
        .executive_white .scan-title { margin-top: 28px; color: #08283a; font-size: 19px; font-weight: 900; }
        .executive_white .scan-note { margin-top: 18px; color: #526170; font-size: 14px; line-height: 1.55; }
        .executive_white .avatar { margin-bottom: 22px; }
        .executive_white .detail { margin-bottom: 24px; }
        .executive_white .venue-row { margin-top: 42px; background: #f8fafc; border-radius: 18px; padding: 22px 24px; }
        .executive_white .venue-table { width: 100%; border-collapse: collapse; }
        .executive_white .venue-table td { width: 50%; vertical-align: top; }

        .boarding_pass .hero { height: 330px; border-radius: 28px; background: #083846; color: #ffffff; padding: 36px 34px; }
        .boarding_pass .logo-wrap { display: inline-block; background: #ffffff; border: 12px solid #e8eef2; border-radius: 20px; padding: 8px 12px; }
        .boarding_pass .logo { width: 250px; height: 68px; }
        .boarding_pass h1 { margin: 36px 0 6px; color: #ffffff; font-size: 40px; line-height: 1.08; }
        .boarding_pass .subtitle { color: #ffffff; font-size: 18px; }
        .boarding_pass .ticket-pill { margin-top: 22px; display: inline-block; border-radius: 999px; background: rgba(255,255,255,.18); color: #ffffff; padding: 11px 22px; font-size: 17px; font-weight: 900; letter-spacing: .08em; }
        .boarding_pass .overlap { margin: -42px 30px 0; width: auto; border-collapse: separate; border-spacing: 0; }
        .boarding_pass .info-card { width: 58%; vertical-align: top; border: 1px solid #dbe3ea; border-radius: 20px; background: #ffffff; padding: 30px 26px; }
        .boarding_pass .divider { width: 4%; border-left: 3px dashed #cbd5e1; }
        .boarding_pass .qr-card { width: 38%; vertical-align: top; border: 1px solid #dbe3ea; border-radius: 20px; background: #ffffff; padding: 38px 26px 28px; text-align: center; }
        .boarding_pass .qr { width: 245px; height: 245px; }
        .boarding_pass .attendee-row { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        .boarding_pass .attendee-row td { vertical-align: middle; }
        .boarding_pass .avatar-cell { width: 92px; }
        .boarding_pass .details { width: 100%; border-collapse: collapse; border-top: 1px dashed #cbd5e1; margin-top: 18px; padding-top: 20px; }
        .boarding_pass .details td { width: 50%; vertical-align: top; padding: 20px 10px 4px 0; }
        .boarding_pass .venue-light { margin: 42px 30px 0; background: #f8fafc; border-color: #dbe3ea; }

        .identity_credential { padding: 18px 12px 0; }
        .identity_credential .top { width: 100%; border-collapse: collapse; margin-bottom: 34px; }
        .identity_credential .top td { vertical-align: middle; }
        .identity_credential .logo { width: 245px; height: 86px; }
        .identity_credential .eyebrow { text-align: right; color: #d4a217; font-size: 18px; letter-spacing: .18em; font-weight: 900; text-transform: uppercase; }
        .identity_credential .rule { height: 5px; background: #08283a; margin-bottom: 34px; }
        .identity_credential .panel { border: 1px solid #dbe3ea; border-radius: 24px; overflow: hidden; background: #ffffff; }
        .identity_credential .panel-head { background: #f8fafc; padding: 38px 32px; }
        .identity_credential .panel-head table { width: 100%; border-collapse: collapse; }
        .identity_credential .panel-head h1 { margin: 0 0 24px; color: #08283a; font-size: 38px; line-height: 1.1; }
        .identity_credential .panel-head .name { color: #111827; font-size: 27px; font-weight: 900; }
        .identity_credential .panel-body { padding: 34px 32px; }
        .identity_credential .panel-body table { width: 100%; border-collapse: collapse; }
        .identity_credential .left { width: 52%; vertical-align: top; padding-right: 28px; }
        .identity_credential .right { width: 48%; vertical-align: top; text-align: center; }
        .identity_credential .qr-box { border-radius: 22px; padding: 32px; display: inline-block; }
        .identity_credential .qr { width: 250px; height: 250px; }
        .identity_credential .field { margin-bottom: 28px; }
        .identity_credential .venue-dark { margin-top: 36px; border-radius: 18px; }

        .qr_hero { border: 1px solid #dbe3ea; border-left: 16px solid #fff2cc; border-radius: 26px; padding: 34px 32px; text-align: center; }
        .qr_hero .header { width: 100%; border-collapse: collapse; text-align: left; padding-bottom: 24px; border-bottom: 1px solid #dbe3ea; margin-bottom: 30px; }
        .qr_hero .header td { vertical-align: middle; }
        .qr_hero .logo { width: 230px; height: 72px; }
        .qr_hero .headline { text-align: left; }
        .qr_hero .eyebrow { color: #d4a217; font-size: 18px; letter-spacing: .18em; font-weight: 900; text-transform: uppercase; }
        .qr_hero h1 { margin: 6px 0 0; color: #08283a; font-size: 34px; line-height: 1.12; }
        .qr_hero .qr-box { display: inline-block; padding: 24px; border-radius: 0; }
        .qr_hero .qr { width: 345px; height: 345px; }
        .qr_hero .ticket-number { margin: 22px auto 22px; border-radius: 999px; background: #08283a; color: #ffffff; padding: 14px 28px; display: inline-block; font-size: 24px; font-weight: 900; letter-spacing: .08em; }
        .qr_hero .prompt { color: #5b6878; font-size: 16px; margin-bottom: 50px; }
        .qr_hero .cards { width: 100%; border-collapse: separate; border-spacing: 14px; text-align: left; }
        .qr_hero .cards td { vertical-align: top; }
        .qr_hero .attendee-card { width: 66%; }
        .qr_hero .status-card { width: 34%; }
        .qr_hero .venue-light { margin: 18px 14px 0; text-align: left; }

        .certificate { border: 3px double #d9a920; border-radius: 26px; padding: 34px 38px; }
        .certificate .head { text-align: center; border-bottom: 1px solid #dbe3ea; padding-bottom: 24px; margin-bottom: 28px; }
        .certificate .logo { width: 240px; height: 78px; }
        .certificate h1 { margin: 8px 0 8px; color: #08283a; font-family: DejaVu Serif, serif; font-size: 42px; line-height: 1.05; }
        .certificate .eyebrow { color: #d4a217; font-size: 18px; letter-spacing: .16em; font-weight: 900; text-transform: uppercase; text-align: center; }
        .certificate .issued { width: 56%; margin: 26px auto 32px; border-collapse: collapse; }
        .certificate .issued td { vertical-align: middle; }
        .certificate .issued-name { font-family: DejaVu Serif, serif; font-size: 33px; font-weight: 900; color: #111827; }
        .certificate .main { width: 100%; border-collapse: collapse; }
        .certificate .qr-cell { width: 46%; vertical-align: top; padding-right: 36px; }
        .certificate .info-cell { width: 54%; vertical-align: top; }
        .certificate .qr-box { border: 1px solid #dbe3ea; border-radius: 20px; padding: 26px; background: #f8fafc; text-align: center; }
        .certificate .qr { width: 275px; height: 275px; }
        .certificate .scan-title { color: #526170; font-size: 15px; font-weight: 900; margin-top: 18px; text-align: center; }
        .certificate .line-item { width: 100%; border-collapse: collapse; border-bottom: 1px solid #dbe3ea; }
        .certificate .line-item td { padding: 14px 0; vertical-align: middle; }
        .certificate .line-item .label-cell { width: 44%; }
        .certificate .line-item .value-cell { width: 56%; text-align: right; }
        .certificate .venue-dark { margin-top: 34px; }
        .certificate .footer-note { margin-top: 34px; }
    </style>
</head>
<body>
    {$this->ticketTemplateMarkup($template, $logo, $qr, $profileImage, $eventTitle, $number, $attendee, $ticketType, $amountPaid, $status, $issuedAt, $venueName, $venueAddress)}
</body>
</html>
HTML;
    }

    private function ticketTemplateMarkup(string $template, string $logo, string $qr, string $profileImage, string $eventTitle, string $number, string $attendee, string $ticketType, string $amountPaid, string $status, string $issuedAt, string $venueName, string $venueAddress): string
    {
        $eventTitle = $this->escapeHtml($eventTitle);
        $number = $this->escapeHtml($number);
        $attendee = $this->escapeHtml($attendee ?: 'Guest');
        $ticketType = $this->escapeHtml($ticketType);
        $amountPaid = $this->escapeHtml($amountPaid);
        $status = $this->escapeHtml(str_replace('_', ' ', $status));
        $issuedAt = $this->escapeHtml($issuedAt);
        $venueName = $this->escapeHtml($venueName);
        $venueAddress = $this->escapeHtml($venueAddress);
        $venueText = trim($venueName.($venueAddress !== '' ? ' · '.$venueAddress : ''));
        $venueAddressBlock = $venueAddress !== ''
            ? '<span class="muted" style="display:block;margin-top:6px;">'.$venueAddress.'</span>'
            : '';
        $avatarCell = $profileImage !== ''
            ? '<td class="avatar-cell">'.$profileImage.'</td>'
            : '';
        $headerAvatar = $profileImage !== ''
            ? '<td style="width:110px;text-align:right;">'.$profileImage.'</td>'
            : '';
        $certificateAvatarCell = $profileImage !== ''
            ? '<td style="width:110px;">'.$profileImage.'</td>'
            : '';

        return match ($template) {
            'boarding_pass' => <<<HTML
    <div class="ticket boarding_pass">
        <div class="hero">
            <div class="logo-wrap"><img class="logo" src="{$logo}" alt="Goshen Camp Retreat"></div>
            <h1>{$eventTitle}</h1>
            <div class="subtitle">Official attendee access pass</div>
            <div class="ticket-pill">{$number}</div>
        </div>
        <table class="overlap"><tr>
            <td class="info-card">
                <table class="attendee-row"><tr>{$avatarCell}<td><span class="label">Attendee</span><span class="value" style="font-size:27px;">{$attendee}</span></td></tr></table>
                <table class="details"><tr><td><span class="label">Ticket type</span><span class="value">{$ticketType}</span></td><td><span class="label">Amount paid</span><span class="value">{$amountPaid}</span></td></tr><tr><td><span class="label">Status</span><span class="status">{$status}</span></td><td><span class="label">Issued at</span><span class="value">{$issuedAt}</span></td></tr></table>
            </td>
            <td class="divider"></td>
            <td class="qr-card"><img class="qr" src="{$qr}" alt="Ticket QR code"><div style="margin-top:28px;font-size:17px;font-weight:900;color:#526170;">Scan at check-in</div><div class="muted" style="font-size:13px;margin-top:6px;">Fast validation for this attendee only.</div></td>
        </tr></table>
        <div class="venue-light"><span class="label">Retreat venue</span><span class="value">{$venueName}</span>{$venueAddressBlock}</div>
    </div>
HTML,
            'identity_credential' => <<<HTML
    <div class="ticket identity_credential">
        <table class="top"><tr><td><img class="logo" src="{$logo}" alt="Goshen Camp Retreat"></td><td class="eyebrow">Official attendee credential</td></tr></table>
        <div class="rule"></div>
        <div class="panel">
            <div class="panel-head"><table><tr><td><h1>{$eventTitle}</h1><div class="name">{$attendee}</div></td>{$headerAvatar}</tr></table></div>
            <div class="panel-body">
                <table><tr>
                    <td class="left">
                        <div class="field"><span class="label">Ticket number</span><span class="value" style="font-size:30px;letter-spacing:.08em;">{$number}</span></div>
                        <div class="card" style="margin-bottom:20px;"><span class="label">Ticket type</span><span class="value">{$ticketType}</span></div>
                        <div class="card" style="margin-bottom:20px;"><span class="label">Amount paid</span><span class="value">{$amountPaid}</span></div>
                        <div class="card"><span class="label">Status</span><span class="status">{$status}</span></div>
                    </td>
                    <td class="right"><div class="qr-box"><img class="qr" src="{$qr}" alt="Ticket QR code"></div><div style="font-weight:900;font-size:17px;margin-top:26px;">Scan for secure check-in</div></td>
                </tr></table>
            </div>
        </div>
        <div class="venue-dark"><span class="label">Retreat venue</span><span class="value">{$venueText}</span></div>
    </div>
HTML,
            'qr_hero' => <<<HTML
    <div class="ticket qr_hero">
        <table class="header"><tr><td style="width:28%;"><img class="logo" src="{$logo}" alt="Goshen Camp Retreat"></td><td class="headline"><div class="eyebrow">Official attendee ticket</div><h1>{$eventTitle}</h1></td>{$headerAvatar}</tr></table>
        <div class="qr-box"><img class="qr" src="{$qr}" alt="Ticket QR code"></div>
        <div class="ticket-number">{$number}</div>
        <div class="prompt">Present this QR code at check-in. It belongs only to the named attendee.</div>
        <table class="cards"><tr><td class="card attendee-card"><span class="label">Attendee</span><span class="value" style="font-size:28px;">{$attendee}</span></td><td class="card status-card"><span class="label">Status</span><span class="status">{$status}</span></td></tr><tr><td class="card"><span class="label">Ticket type</span><span class="value">{$ticketType}</span></td><td class="card"><span class="label">Amount paid</span><span class="value">{$amountPaid}</span></td></tr><tr><td class="card"><span class="label">Issued at</span><span class="value">{$issuedAt}</span></td><td></td></tr></table>
        <div class="venue-light"><span class="label">Retreat venue</span><span class="value">{$venueName}</span>{$venueAddressBlock}</div>
    </div>
HTML,
            'certificate' => <<<HTML
    <div class="ticket certificate">
        <div class="head"><img class="logo" src="{$logo}" alt="Goshen Camp Retreat"><h1>{$eventTitle}</h1><div class="eyebrow">Official attendee ticket</div></div>
        <table class="issued"><tr>{$certificateAvatarCell}<td><span class="label">Issued to</span><span class="issued-name">{$attendee}</span></td></tr></table>
        <table class="main"><tr>
            <td class="qr-cell"><div class="qr-box"><img class="qr" src="{$qr}" alt="Ticket QR code"><div class="scan-title">Scan this QR code at check-in</div></div></td>
            <td class="info-cell">
                <table class="line-item"><tr><td class="label-cell"><span class="label">Ticket number</span></td><td class="value-cell"><span class="value">{$number}</span></td></tr></table>
                <table class="line-item"><tr><td class="label-cell"><span class="label">Ticket type</span></td><td class="value-cell"><span class="value">{$ticketType}</span></td></tr></table>
                <table class="line-item"><tr><td class="label-cell"><span class="label">Amount paid</span></td><td class="value-cell"><span class="value">{$amountPaid}</span></td></tr></table>
                <table class="line-item"><tr><td class="label-cell"><span class="label">Status</span></td><td class="value-cell"><span class="status">{$status}</span></td></tr></table>
                <table class="line-item"><tr><td class="label-cell"><span class="label">Issued at</span></td><td class="value-cell"><span class="value">{$issuedAt}</span></td></tr></table>
            </td>
        </tr></table>
        <div class="venue-dark"><span class="label">Retreat venue</span><span class="value">{$venueText}</span></div>
        <div class="footer-note">Please keep this PDF accessible on your phone or printed copy. The QR code is unique to this attendee and should only be used by the named ticket holder.</div>
    </div>
HTML,
            default => <<<HTML
    <div class="ticket executive_white">
        <div class="header"><img class="logo" src="{$logo}" alt="Goshen Camp Retreat"><h1>{$eventTitle}</h1><div class="subtitle">Official attendee ticket</div></div>
        <table class="main"><tr>
            <td class="qr-cell"><div class="qr-box"><img class="qr" src="{$qr}" alt="Ticket QR code"></div><div class="scan-title">Scan this QR code at check-in</div><div class="scan-note">Keep this PDF accessible on your phone or printed copy. The QR code is unique to the named ticket holder.</div></td>
            <td class="info-cell">{$profileImage}<div class="detail"><span class="label">Attendee</span><span class="value">{$attendee}</span></div><div class="detail"><span class="label">Ticket number</span><span class="value">{$number}</span></div><div class="detail"><span class="label">Ticket type</span><span class="value">{$ticketType}</span></div><div class="detail"><span class="label">Amount paid</span><span class="value">{$amountPaid}</span></div><div class="detail"><span class="label">Status</span><span class="status">{$status}</span></div></td>
        </tr></table>
        <div class="venue-row"><table class="venue-table"><tr><td><span class="label">Venue</span><span class="value">{$venueName}</span>{$venueAddressBlock}</td><td><span class="label">Issued at</span><span class="value">{$issuedAt}</span></td></tr></table></div>
    </div>
HTML,
        };
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function dompdfTicketPdf(Ticket $ticket): string
    {
        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->ticketHtml($ticket));
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    private function imageDataUri(string $path, string $mimeType): string
    {
        if (! is_file($path)) {
            return '';
        }

        return 'data:'.$mimeType.';base64,'.base64_encode((string) file_get_contents($path));
    }

    private function profileImageDataUri(Ticket $ticket): string
    {
        $avatar = trim((string) $this->ticketOwner($ticket)?->avatar);

        if ($avatar === '' || str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return '';
        }

        $path = Storage::disk('public')->path($avatar);
        if (! is_file($path)) {
            return '';
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };

        if ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp') && function_exists('imagepng')) {
            $image = @imagecreatefromwebp($path);
            if ($image) {
                ob_start();
                imagepng($image);
                imagedestroy($image);

                return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
            }
        }

        return $mimeType ? $this->imageDataUri($path, $mimeType) : '';
    }

    private function ticketOwner(Ticket $ticket): ?MobileUser
    {
        $customerId = (int) ($ticket->booking?->customer_id ?? 0);
        if ($customerId > 0) {
            return MobileUser::query()->find($customerId);
        }

        $email = trim((string) ($ticket->booking?->customer_email ?: $ticket->attendee?->email));
        if ($email === '') {
            return null;
        }

        return MobileUser::query()->where('email', $email)->first();
    }

    private function qrDataUri(Ticket $ticket): string
    {
        $svg = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'imageBase64' => false,
            'scale' => 8,
        ])))->render($this->qrPayload->encodedPayloadFor($ticket));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function basicTicketPdf(Ticket $ticket): string
    {
        $attendee = trim(($ticket->attendee?->first_name ?? '').' '.($ticket->attendee?->last_name ?? ''));
        $number = $ticket->formatted_number ?: $ticket->ticket_number ?: $ticket->public_id;
        $event = $ticket->event?->name ?: 'Goshen Retreat';
        $type = $ticket->ticketType?->name ?: 'Ticket';
        $amountPaid = $this->amountPaidLabel($ticket);
        $status = $ticket->status instanceof \BackedEnum
            ? $ticket->status->value
            : (string) $ticket->status;

        $lines = [
            'Goshen Retreat Ticket',
            $event,
            'Ticket: '.$number,
            'Attendee: '.($attendee ?: 'Guest'),
            'Type: '.$type,
            'Amount paid: '.$amountPaid,
            'Status: '.$status,
            '',
            'Open the Goshen web app ticket page to scan the live QR code.',
        ];

        $content = "BT\n/F1 24 Tf\n72 760 Td\n";
        foreach ($lines as $index => $line) {
            if ($index === 1) {
                $content .= "/F1 16 Tf\n";
            } elseif ($index === 2) {
                $content .= "/F1 13 Tf\n";
            }

            $content .= '('.$this->pdfText($line).") Tj\n0 -26 Td\n";
        }
        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function pdfText(string $value): string
    {
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? '';

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
    }

    private function amountPaidLabel(Ticket $ticket): string
    {
        $ticket->loadMissing('booking.installments');
        $metadata = is_array($ticket->metadata) ? $ticket->metadata : [];
        $amount = $metadata['amount_paid'] ?? $metadata['historical_paid_amount'] ?? null;
        if (! is_numeric($amount) || (float) $amount <= 0) {
            $booking = $ticket->booking;
            $amount = $booking
                ? max((float) $booking->paid_total, (float) $booking->installments->sum('paid_amount'))
                : 0;
        }

        $currency = strtoupper((string) ($ticket->booking?->currency ?: $ticket->ticketType?->currency ?: 'GBP'));

        return (float) $amount > 0
            ? trim($currency.' '.number_format((float) $amount, 2))
            : 'Not recorded';
    }

    private function escapeIcs(string $value): string
    {
        return str_replace(['\\', "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\\,', '\\;'], $value);
    }
}
