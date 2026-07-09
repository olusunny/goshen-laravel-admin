<?php

namespace Personal\EventInstallments\Http\Controllers\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketDocument;
use Personal\EventInstallments\Services\TicketDocumentService;

class TicketDocumentController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Ticket $ticket, string $type, TicketDocumentService $documents)
    {
        $this->authorize('download', $ticket);

        if (! in_array($type, ['qr', 'pdf', 'ics'], true)) {
            throw ValidationException::withMessages(['type' => 'Unsupported ticket document type.']);
        }

        $document = $ticket->documents()->where('type', $type)->first()
            ?: $this->generate($documents, $ticket, $type);

        return Storage::disk($document->disk)->download($document->path, $this->filename($ticket, $document));
    }

    private function generate(TicketDocumentService $documents, Ticket $ticket, string $type): TicketDocument
    {
        return match ($type) {
            'qr' => $documents->generateQr($ticket),
            'pdf' => $documents->generatePdf($ticket),
            'ics' => $documents->generateIcs($ticket),
        };
    }

    private function filename(Ticket $ticket, TicketDocument $document): string
    {
        $number = $ticket->formatted_number ?: $ticket->ticket_number;
        $extension = match ($document->type) {
            'qr' => 'png',
            'pdf' => 'pdf',
            'ics' => 'ics',
            default => 'bin',
        };

        return $number . '.' . $extension;
    }
}
