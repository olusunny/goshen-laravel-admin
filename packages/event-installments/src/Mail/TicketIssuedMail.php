<?php

namespace Personal\EventInstallments\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Personal\EventInstallments\Models\Ticket;

class TicketIssuedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array<int, array{disk: string, path: string, name: string, mime: string|null}> $attachments
     */
    public function __construct(
        public readonly Ticket $ticket,
        public readonly array $documentAttachments = [],
    ) {
    }

    public function build(): self
    {
        $this->ticket->loadMissing(['event', 'booking', 'attendee', 'ticketType']);

        $mail = $this
            ->subject((string) config('event-installments.ticket.email.subject', 'Your event ticket'))
            ->view('event-installments::emails.ticket-issued', [
                'ticket' => $this->ticket,
                'event' => $this->ticket->event,
                'booking' => $this->ticket->booking,
                'attendee' => $this->ticket->attendee,
            ]);

        $fromAddress = config('event-installments.ticket.email.from_address');
        if (is_string($fromAddress) && $fromAddress !== '') {
            $mail->from($fromAddress, config('event-installments.ticket.email.from_name'));
        }

        foreach ($this->documentAttachments as $attachment) {
            $mail->attachFromStorageDisk(
                $attachment['disk'],
                $attachment['path'],
                $attachment['name'],
                ['mime' => $attachment['mime']],
            );
        }

        return $mail;
    }
}
