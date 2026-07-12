<?php

namespace Personal\EventInstallments\Services;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketDocument;
use RuntimeException;

class TicketDocumentService
{
    public function __construct(private readonly QrPayloadService $qrPayload)
    {
    }

    public function generateQr(Ticket $ticket): TicketDocument
    {
        if (! class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            throw new RuntimeException('Install simplesoftwareio/simple-qrcode to generate QR images.');
        }

        $disk = config('event-installments.storage.disk');
        $path = trim(config('event-installments.storage.qr_path'), '/') . '/' . $ticket->public_id . '.png';
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
        $path = trim(config('event-installments.storage.pdf_path'), '/') . '/' . $ticket->public_id . '.pdf';

        $pdf = class_exists(\Dompdf\Dompdf::class)
            ? $this->dompdfTicketPdf($ticket)
            : (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)
                ? \Barryvdh\DomPDF\Facade\Pdf::loadHTML($this->ticketHtml($ticket))->output()
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
        $path = trim(config('event-installments.storage.ics_path'), '/') . '/' . $ticket->public_id . '.ics';
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Personal Event Installments//EN',
            'BEGIN:VEVENT',
            'UID:' . $ticket->public_id,
            'SUMMARY:' . $this->escapeIcs($ticket->event->name),
            'DTSTART:' . $schedule->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:' . ($schedule->ends_at ?: $schedule->starts_at)->utc()->format('Ymd\THis\Z'),
            'LOCATION:' . $this->escapeIcs((string) $ticket->event->venue_name),
            'DESCRIPTION:' . $this->escapeIcs('Ticket ' . ($ticket->formatted_number ?: $ticket->ticket_number)),
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
        $attendee = trim(($ticket->attendee?->first_name ?? '') . ' ' . ($ticket->attendee?->last_name ?? ''));
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

        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f2430; background: #ffffff; }
        .ticket { border: 2px solid #e5c33f; padding: 24px 28px 22px; border-radius: 18px; }
        .header { text-align: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 14px; margin-bottom: 20px; }
        .logo { width: 245px; max-height: 88px; object-fit: contain; margin: 0 auto 8px; }
        h1 { margin: 0; font-size: 24px; letter-spacing: 0.2px; color: #0a2a3a; }
        .subtitle { margin-top: 5px; font-size: 13px; color: #6b7280; }
        table.layout { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .qr-cell { width: 54%; vertical-align: top; padding-right: 22px; text-align: center; }
        .info-cell { width: 46%; vertical-align: top; padding-left: 18px; border-left: 1px solid #e5e7eb; }
        .qr-box { border: 2px solid #0f2430; border-radius: 14px; padding: 16px; display: inline-block; background: #ffffff; }
        .qr { width: 285px; height: 285px; object-fit: contain; }
        .scan-title { margin-top: 12px; font-weight: 700; font-size: 15px; color: #0f2430; }
        .scan-note { margin-top: 5px; font-size: 11.5px; line-height: 1.5; color: #4b5563; }
        .detail { margin-bottom: 12px; }
        .label { display: block; text-transform: uppercase; letter-spacing: .08em; font-size: 9.5px; font-weight: 700; color: #8a6b00; }
        .value { display: block; margin-top: 2px; font-size: 14px; line-height: 1.35; color: #111827; }
        .ticket-number { font-size: 17px; font-weight: 800; color: #0a2a3a; }
        .status { display: inline-block; margin-top: 4px; padding: 5px 9px; border-radius: 999px; background: #ecfdf5; color: #047857; font-size: 12px; font-weight: 700; }
        .below { margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
        .below table { width: 100%; border-collapse: collapse; }
        .below td { width: 50%; vertical-align: top; padding: 6px 12px 6px 0; }
        .footer-note { margin-top: 15px; padding: 10px 12px; border-radius: 10px; background: #f8fafc; color: #475569; font-size: 11px; line-height: 1.45; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <img class="logo" src="{$logo}" alt="Goshen Camp Retreat">
            <h1>{$this->escapeHtml($eventTitle)}</h1>
            <div class="subtitle">Official attendee ticket</div>
        </div>

        <table class="layout">
            <tr>
                <td class="qr-cell">
                    <div class="qr-box">
                        <img class="qr" src="{$qr}" alt="Ticket QR code">
                    </div>
                    <div class="scan-title">Scan this QR code at check-in</div>
                    <div class="scan-note">You can also open the live ticket page in the Goshen web app or mobile app and scan the QR code from there.</div>
                </td>
                <td class="info-cell">
                    <div class="detail">
                        <span class="label">Ticket number</span>
                        <span class="value ticket-number">{$this->escapeHtml($number)}</span>
                    </div>
                    <div class="detail">
                        <span class="label">Attendee</span>
                        <span class="value">{$this->escapeHtml($attendee ?: 'Guest')}</span>
                    </div>
                    <div class="detail">
                        <span class="label">Ticket type</span>
                        <span class="value">{$this->escapeHtml($ticketType)}</span>
                    </div>
                    <div class="detail">
                        <span class="label">Amount paid</span>
                        <span class="value">{$this->escapeHtml($amountPaid)}</span>
                    </div>
                    <div class="detail">
                        <span class="label">Status</span>
                        <span class="status">{$this->escapeHtml(str_replace('_', ' ', $status))}</span>
                    </div>
                </td>
            </tr>
        </table>

        <div class="below">
            <table>
                <tr>
                    <td>
                        <span class="label">Event</span>
                        <span class="value">{$this->escapeHtml($eventTitle)}</span>
                    </td>
                    <td>
                        <span class="label">Issued at</span>
                        <span class="value">{$this->escapeHtml($issuedAt)}</span>
                    </td>
                </tr>
            </table>
            <div class="footer-note">
                Please keep this PDF accessible on your phone or printed copy. The QR code is unique to this attendee and should only be used by the named ticket holder.
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function dompdfTicketPdf(Ticket $ticket): string
    {
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new \Dompdf\Dompdf($options);
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
        $attendee = trim(($ticket->attendee?->first_name ?? '') . ' ' . ($ticket->attendee?->last_name ?? ''));
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
            'Ticket: ' . $number,
            'Attendee: ' . ($attendee ?: 'Guest'),
            'Type: ' . $type,
            'Amount paid: ' . $amountPaid,
            'Status: ' . $status,
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

            $content .= '(' . $this->pdfText($line) . ") Tj\n0 -26 Td\n";
        }
        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        return $pdf . "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
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
            ? trim($currency . ' ' . number_format((float) $amount, 2))
            : 'Not recorded';
    }

    private function escapeIcs(string $value): string
    {
        return str_replace(["\\", "\n", "\r", ",", ";"], ["\\\\", "\\n", '', "\\,", "\\;"], $value);
    }
}
