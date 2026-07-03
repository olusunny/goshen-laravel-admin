<?php

namespace Personal\EventInstallments\Policies;

use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Support\AuthorizesEventInstallments;

class TicketPolicy
{
    use AuthorizesEventInstallments;

    public function view($user, Ticket $ticket): bool
    {
        return $this->managesEvent($user, $ticket->event);
    }

    public function checkIn($user, Ticket $ticket): bool
    {
        return $this->checksIn($user) || $this->managesEvent($user, $ticket->event);
    }

    public function download($user, Ticket $ticket): bool
    {
        return $this->managesEvent($user, $ticket->event)
            || (string) $ticket->booking->customer_id === (string) $user?->getAuthIdentifier();
    }

    public function email($user, Ticket $ticket): bool
    {
        return $this->managesEvent($user, $ticket->event);
    }
}
