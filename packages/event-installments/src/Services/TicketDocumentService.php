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
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            throw new RuntimeException('Install barryvdh/laravel-dompdf to generate PDF tickets.');
        }

        $ticket->loadMissing(['event', 'booking', 'attendee', 'ticketType']);
        $disk = config('event-installments.storage.disk');
        $path = trim(config('event-installments.storage.pdf_path'), '/') . '/' . $ticket->public_id . '.pdf';

        $html = $this->ticketHtml($ticket);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();

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

    private function escapeIcs(string $value): string
    {
        return str_replace(["\\", "\n", "\r", ",", ";"], ["\\\\", "\\n", '', "\\,", "\\;"], $value);
    }
}
