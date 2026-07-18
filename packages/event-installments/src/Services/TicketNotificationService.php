<?php

namespace Personal\EventInstallments\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Personal\EventInstallments\Mail\TicketIssuedMail;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketDocument;
use Personal\EventInstallments\Models\TicketEmailLog;
use RuntimeException;
use Throwable;

class TicketNotificationService
{
    public function __construct(private readonly TicketDocumentService $documents) {}

    public function sendTicket(Ticket $ticket, ?string $recipient = null): TicketEmailLog
    {
        $ticket->loadMissing(['event', 'booking', 'attendee', 'ticketType']);

        $recipient = trim((string) ($recipient ?: ($ticket->attendee?->email ?: $ticket->booking->customer_email)));
        $subject = (string) config('event-installments.ticket.email.subject', 'Your event ticket');

        $log = TicketEmailLog::query()->create([
            'ticket_id' => $ticket->id,
            'booking_id' => $ticket->booking_id,
            'recipient' => $recipient,
            'subject' => $subject,
            'status' => 'pending',
            'attachments' => [],
        ]);

        try {
            if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('A valid recipient email address is required before the ticket can be sent.');
            }

            if (! config('event-installments.ticket.email.enabled', true)) {
                throw new RuntimeException('Ticket email sending is currently disabled.');
            }

            $attachments = $this->buildAttachments($ticket);

            Mail::to($recipient)->send(new TicketIssuedMail($ticket, $attachments));

            $log->forceFill([
                'status' => 'sent',
                'attachments' => $attachments,
                'sent_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ])->save();

            report($exception);
        }

        return $log;
    }

    public function sendForBookingTickets(int $bookingId): int
    {
        $sent = 0;

        Ticket::query()
            ->where('booking_id', $bookingId)
            ->with(['event', 'booking', 'attendee', 'ticketType'])
            ->each(function (Ticket $ticket) use (&$sent) {
                $log = $this->sendTicket($ticket);

                if ($log->status === 'sent') {
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * @return array<int, array{disk: string, path: string, name: string, mime: string|null}>
     */
    private function buildAttachments(Ticket $ticket): array
    {
        $attachments = [];

        $attachment = $this->optionalAttachment($ticket, 'qr');

        if ($attachment !== null) {
            $attachments[] = $attachment;
        }

        if (config('event-installments.ticket.email.attach_pdf', true)) {
            $attachments[] = $this->requiredAttachment($ticket, 'pdf');
        }

        if (config('event-installments.ticket.email.attach_ics', true)) {
            $attachment = $this->optionalAttachment($ticket, 'ics');

            if ($attachment !== null) {
                $attachments[] = $attachment;
            }
        }

        return $attachments;
    }

    /**
     * @return array{disk: string, path: string, name: string, mime: string|null}|null
     */
    private function optionalAttachment(Ticket $ticket, string $type): ?array
    {
        try {
            $document = $ticket->documents()->where('type', $type)->first() ?: match ($type) {
                'qr' => $this->documents->generateQr($ticket),
                'pdf' => $this->documents->generatePdf($ticket),
                'ics' => $this->documents->generateIcs($ticket),
                default => null,
            };

            return $document instanceof TicketDocument
                ? $this->attachmentFromDocument($document, $ticket)
                : null;
        } catch (Throwable $exception) {
            Log::warning('Ticket email attachment could not be generated.', [
                'ticket_id' => $ticket->id,
                'ticket_public_id' => $ticket->public_id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{disk: string, path: string, name: string, mime: string|null}
     */
    private function requiredAttachment(Ticket $ticket, string $type): array
    {
        try {
            $document = $ticket->documents()->where('type', $type)->first() ?: match ($type) {
                'qr' => $this->documents->generateQr($ticket),
                'pdf' => $this->documents->generatePdf($ticket),
                'ics' => $this->documents->generateIcs($ticket),
                default => null,
            };

            if (! $document instanceof TicketDocument) {
                throw new RuntimeException("Unsupported required ticket attachment type [{$type}].");
            }

            return $this->attachmentFromDocument($document, $ticket);
        } catch (Throwable $exception) {
            Log::warning('Required ticket email attachment could not be generated.', [
                'ticket_id' => $ticket->id,
                'ticket_public_id' => $ticket->public_id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                strtoupper($type).' ticket attachment could not be generated: '.$exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    /**
     * @return array{disk: string, path: string, name: string, mime: string|null}
     */
    private function attachmentFromDocument(TicketDocument $document, Ticket $ticket): array
    {
        $number = $ticket->formatted_number ?: $ticket->ticket_number;
        $extension = match ($document->type) {
            'pdf' => 'pdf',
            'ics' => 'ics',
            'qr' => 'png',
            default => 'bin',
        };

        return [
            'disk' => $document->disk,
            'path' => $document->path,
            'name' => $number.'.'.$extension,
            'mime' => $document->mime_type,
        ];
    }
}
