<?php

namespace Personal\EventInstallments\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Services\TicketNotificationService;

class TicketEmailController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Ticket $ticket, TicketNotificationService $notifications)
    {
        $this->authorize('email', $ticket);

        $recipient = $request->input('recipient');
        $log = $notifications->sendTicket($ticket, is_string($recipient) && $recipient !== '' ? $recipient : null);

        if ($log->status !== 'sent') {
            return back()->withErrors([
                'ticket_email' => $log->error ?: 'The ticket email could not be sent. Please check mail and ticket PDF generation settings.',
            ]);
        }

        return back()->with('status', 'Ticket email sent to ' . $log->recipient . '.');
    }
}
