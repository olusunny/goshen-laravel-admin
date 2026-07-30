<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Services\TicketNotificationService;

class SendGoshenTicketEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $ticketId) {}

    public function handle(TicketNotificationService $notifications): void
    {
        $ticket = Ticket::query()->find($this->ticketId);

        if ($ticket) {
            $notifications->sendTicket($ticket);
        }
    }
}
