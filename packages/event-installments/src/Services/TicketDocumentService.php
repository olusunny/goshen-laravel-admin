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
        $venue = trim(implode(' · ', array_filter([$venueName, $venueAddress])));
        $venue = $venue !== '' ? $venue : 'Venue details will be shared by the church.';
        $template = app(GoshenTicketPdfTemplateSettings::class)->active();
        $profileExecutive = $profile !== ''
            ? '<div class="person"><img class="avatar" src="'.$profile.'" alt="Attendee profile image"><div><span class="label">Attendee</span><span class="value">'.$this->escapeHtml($attendee ?: 'Guest').'</span></div></div>'
            : '<div class="detail"><span class="label">Attendee</span><span class="value">'.$this->escapeHtml($attendee ?: 'Guest').'</span></div>';
        $profileInline = $profile !== ''
            ? '<img class="avatar" src="'.$profile.'" alt="Attendee profile image">'
            : '';

        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; background: #ffffff; }
        .ticket { background:#ffffff; min-height: 1010px; border-radius: 18px; }
        .logo { width: 245px; max-height: 88px; object-fit: contain; margin: 0 auto 8px; }
        h1 { margin: 0; font-size: 25px; letter-spacing: 0.2px; color: #08283a; line-height:1.15; }
        .subtitle { margin-top: 5px; font-size: 13px; color: #526170; }
        table.layout { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .qr-cell { width: 54%; vertical-align: top; padding-right: 22px; text-align: center; }
        .info-cell { width: 46%; vertical-align: top; padding-left: 18px; border-left: 1px solid #e5e7eb; }
        .qr-box { border: 2px solid #0f2430; border-radius: 14px; padding: 16px; display: inline-block; background: #ffffff; }
        .qr { width: 285px; height: 285px; object-fit: contain; image-rendering: pixelated; }
        .scan-title { margin-top: 12px; font-weight: 800; font-size: 15px; color: #0f2430; }
        .scan-note { margin-top: 5px; font-size: 11.5px; line-height: 1.5; color: #334155; }
        .detail { margin-bottom: 12px; }
        .label { display: block; text-transform: uppercase; letter-spacing: .08em; font-size: 9.5px; font-weight: 800; color: #8a6b00; }
        .value { display: block; margin-top: 2px; font-size: 14px; line-height: 1.35; color: #111827; font-weight: 600; }
        .ticket-number { font-size: 17px; font-weight: 800; color: #0a2a3a; }
        .status { display: inline-block; margin-top: 4px; padding: 5px 9px; border-radius: 999px; background: #ecfdf5; color: #047857; font-size: 12px; font-weight: 700; }
        .below { margin-top: 20px; padding-top: 16px; border-top: 1px solid #dbe3ea; }
        .below table { width: 100%; border-collapse: collapse; }
        .below td { width: 50%; vertical-align: top; padding: 6px 12px 6px 0; }
        .footer-note { margin-top: 15px; padding: 10px 12px; border-radius: 10px; background: #f8fafc; color: #334155; font-size: 11px; line-height: 1.45; }
        .avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid #ffffff; box-shadow: 0 6px 18px rgba(8,40,58,.18); }
        .person { margin-bottom: 16px; }
        .person .avatar { display:inline-block; vertical-align:middle; margin-right:10px; }
        .person div { display:inline-block; vertical-align:middle; }
        .venue-card { margin-top: 14px; padding: 12px 14px; border-radius: 12px; background:#fff8df; border:1px solid #ecd37c; }
        .executive_white { border: 2px solid #d9a920; padding: 24px 28px 22px; }
        .executive_white .header { text-align:center; border-bottom:1px solid #dbe3ea; padding-bottom:14px; margin-bottom:20px; }
        .boarding_pass { padding:0; overflow:hidden; border:1px solid #dbe3ea; }
        .boarding_pass .hero { padding:28px; background:#08283a; color:#ffffff; }
        .boarding_pass .hero h1 { color:#ffffff; font-size:30px; }
        .boarding_pass .hero .subtitle { color:#dbeafe; }
        .boarding_pass .body { padding:26px 28px; }
        .boarding_pass .stub { width:36%; vertical-align:top; padding-left:18px; border-left:2px dashed #cbd5e1; text-align:center; }
        .boarding_pass .info-main { width:64%; vertical-align:top; padding-right:22px; }
        .identity_credential { padding:28px; border-top:8px solid #08283a; border-left:1px solid #dbe3ea; border-right:1px solid #dbe3ea; border-bottom:1px solid #dbe3ea; }
        .identity_credential .header { border-bottom:4px solid #08283a; padding-bottom:18px; margin-bottom:22px; }
        .identity_credential .identity { padding:16px; border-radius:16px; background:#f8fafc; margin-bottom:20px; }
        .identity_credential .identity h2 { margin:0; font-size:28px; color:#111827; }
        .qr_hero { padding:28px; border-left:8px solid #d9a920; text-align:center; }
        .qr_hero .qr { width:335px; height:335px; }
        .qr_hero .qr-box { padding:18px; margin-top:22px; }
        .qr_hero .details-grid td { width:33.33%; padding:12px; border:1px solid #dbe3ea; border-radius:10px; }
        .certificate { padding:28px; border:4px double #d9a920; text-align:center; }
        .certificate h1 { font-family: DejaVu Serif, serif; font-size:32px; }
        .certificate .attendee-name { font-family: DejaVu Serif, serif; font-size:28px; font-weight:800; color:#111827; margin:8px 0 18px; }
        .certificate .main td { vertical-align:top; }
        .certificate .line { padding:10px 0; border-bottom:1px solid #dbe3ea; text-align:left; }
    </style>
</head>
<body>
    {$this->ticketTemplateMarkup($template, $logo, $qr, $profileInline, $profileExecutive, $eventTitle, $number, $attendee, $ticketType, $amountPaid, $status, $issuedAt, $venue)}
</body>
</html>
HTML;
    }

    private function ticketTemplateMarkup(string $template, string $logo, string $qr, string $profileInline, string $profileExecutive, string $eventTitle, string $number, string $attendee, string $ticketType, string $amountPaid, string $status, string $issuedAt, string $venue): string
    {
        $eventTitle = $this->escapeHtml($eventTitle);
        $number = $this->escapeHtml($number);
        $attendee = $this->escapeHtml($attendee ?: 'Guest');
        $ticketType = $this->escapeHtml($ticketType);
        $amountPaid = $this->escapeHtml($amountPaid);
        $status = $this->escapeHtml(str_replace('_', ' ', $status));
        $issuedAt = $this->escapeHtml($issuedAt);
        $venue = $this->escapeHtml($venue);

        return match ($template) {
            'boarding_pass' => <<<HTML
    <div class="ticket boarding_pass">
        <div class="hero"><img class="logo" src="{$logo}" alt="Goshen Camp Retreat"><h1>{$eventTitle}</h1><div class="subtitle">Official attendee access pass</div><div class="status">{$number}</div></div>
        <div class="body">
            <table class="layout"><tr><td class="info-main">{$profileExecutive}<div class="detail"><span class="label">Ticket type</span><span class="value">{$ticketType}</span></div><div class="detail"><span class="label">Amount paid</span><span class="value">{$amountPaid}</span></div><div class="detail"><span class="label">Issued at</span><span class="value">{$issuedAt}</span></div><div class="detail"><span class="label">Status</span><span class="status">{$status}</span></div><div class="venue-card"><span class="label">Retreat venue</span><span class="value">{$venue}</span></div></td><td class="stub"><div class="qr-box"><img class="qr" src="{$qr}" alt="Ticket QR code"></div><div class="scan-title">Scan at check-in</div></td></tr></table>
            <div class="footer-note">Please keep this PDF accessible on your phone or printed copy. The QR code is unique to this attendee.</div>
        </div>
    </div>
HTML,
            'identity_credential' => <<<HTML
    <div class="ticket identity_credential">
        <div class="header"><img class="logo" src="{$logo}" alt="Goshen Camp Retreat"><h1>{$eventTitle}</h1><div class="subtitle">Official attendee credential</div></div>
        <div class="identity">{$profileInline}<span class="label">Attendee</span><h2>{$attendee}</h2></div>
        <table class="layout"><tr><td class="info-cell" style="border-left:0;padding-left:0;"><div class="detail"><span class="label">Ticket number</span><span class="value ticket-number">{$number}</span></div><div class="detail"><span class="label">Ticket type</span><span class="value">{$ticketType}</span></div><div class="detail"><span class="label">Amount paid</span><span class="value">{$amountPaid}</span></div><div class="detail"><span class="label">Status</span><span class="status">{$status}</span></div></td><td class="qr-cell" style="padding-right:0;"><div class="qr-box"><img class="qr" src="{$qr}" alt="Ticket QR code"></div></td></tr></table>
        <div class="venue-card"><span class="label">Retreat venue</span><span class="value">{$venue}</span></div><div class="footer-note">Issued at {$issuedAt}. Present this credential for secure check-in.</div>
    </div>
HTML,
            'qr_hero' => <<<HTML
    <div class="ticket qr_hero">
        <img class="logo" src="{$logo}" alt="Goshen Camp Retreat"><h1>{$eventTitle}</h1><div class="subtitle">Official attendee ticket</div>
        <div class="qr-box"><img class="qr" src="{$qr}" alt="Ticket QR code"></div><div class="scan-title">Scan this QR code at check-in</div>
        <div class="detail"><span class="label">Ticket number</span><span class="value ticket-number">{$number}</span></div>{$profileExecutive}
        <table class="layout details-grid"><tr><td><span class="label">Ticket type</span><span class="value">{$ticketType}</span></td><td><span class="label">Amount paid</span><span class="value">{$amountPaid}</span></td><td><span class="label">Status</span><span class="status">{$status}</span></td></tr></table>
        <div class="venue-card"><span class="label">Retreat venue</span><span class="value">{$venue}</span></div><div class="footer-note">Issued at {$issuedAt}. This QR code is unique to the named ticket holder.</div>
    </div>
HTML,
            'certificate' => <<<HTML
    <div class="ticket certificate">
        <img class="logo" src="{$logo}" alt="Goshen Camp Retreat"><h1>{$eventTitle}</h1><div class="subtitle">Official attendee ticket</div>{$profileInline}<div class="label">Issued to</div><div class="attendee-name">{$attendee}</div>
        <table class="layout main"><tr><td class="qr-cell" style="width:42%;"><div class="qr-box"><img class="qr" src="{$qr}" alt="Ticket QR code"></div><div class="scan-title">Scan at check-in</div></td><td style="width:58%;padding-left:20px;"><div class="line"><span class="label">Ticket number</span><span class="value">{$number}</span></div><div class="line"><span class="label">Ticket type</span><span class="value">{$ticketType}</span></div><div class="line"><span class="label">Amount paid</span><span class="value">{$amountPaid}</span></div><div class="line"><span class="label">Status</span><span class="status">{$status}</span></div><div class="line"><span class="label">Issued at</span><span class="value">{$issuedAt}</span></div></td></tr></table>
        <div class="venue-card"><span class="label">Retreat venue</span><span class="value">{$venue}</span></div><div class="footer-note">Please keep this PDF accessible on your phone or printed copy.</div>
    </div>
HTML,
            default => <<<HTML
    <div class="ticket executive_white">
        <div class="header"><img class="logo" src="{$logo}" alt="Goshen Camp Retreat"><h1>{$eventTitle}</h1><div class="subtitle">Official attendee ticket</div></div>
        <table class="layout"><tr><td class="qr-cell"><div class="qr-box"><img class="qr" src="{$qr}" alt="Ticket QR code"></div><div class="scan-title">Scan this QR code at check-in</div><div class="scan-note">Open the live ticket page in the Goshen web or mobile app when needed.</div></td><td class="info-cell">{$profileExecutive}<div class="detail"><span class="label">Ticket number</span><span class="value ticket-number">{$number}</span></div><div class="detail"><span class="label">Ticket type</span><span class="value">{$ticketType}</span></div><div class="detail"><span class="label">Amount paid</span><span class="value">{$amountPaid}</span></div><div class="detail"><span class="label">Status</span><span class="status">{$status}</span></div></td></tr></table>
        <div class="below"><table><tr><td><span class="label">Event</span><span class="value">{$eventTitle}</span></td><td><span class="label">Issued at</span><span class="value">{$issuedAt}</span></td></tr></table><div class="venue-card"><span class="label">Retreat venue</span><span class="value">{$venue}</span></div><div class="footer-note">Please keep this PDF accessible on your phone or printed copy. The QR code is unique to this attendee and should only be used by the named ticket holder.</div></div>
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
