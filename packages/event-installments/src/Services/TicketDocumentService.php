<?php

namespace Personal\EventInstallments\Services;

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

        $pdf = class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)
            ? \Barryvdh\DomPDF\Facade\Pdf::loadHTML($this->ticketHtml($ticket))->output()
            : $this->basicTicketPdf($ticket);

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

        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; }
        .ticket { border: 1px solid #d1d5db; padding: 24px; border-radius: 8px; }
        h1 { margin: 0 0 12px; font-size: 24px; }
        .meta { margin-top: 16px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="ticket">
        <h1>{$this->escapeHtml($ticket->event->name)}</h1>
        <div class="meta">
            <div><strong>Ticket:</strong> {$this->escapeHtml($number)}</div>
            <div><strong>Attendee:</strong> {$this->escapeHtml($attendee ?: 'Guest')}</div>
            <div><strong>Type:</strong> {$this->escapeHtml($ticket->ticketType->name)}</div>
            <div><strong>Amount paid:</strong> {$this->escapeHtml($amountPaid)}</div>
            <div><strong>Status:</strong> {$this->escapeHtml($ticket->status->value)}</div>
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
