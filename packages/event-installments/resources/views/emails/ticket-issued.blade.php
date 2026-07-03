<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} Ticket</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h1 style="margin-bottom: 8px;">{{ $event->name }}</h1>
    <p>Your Goshen Retreat ticket is ready. Please keep this email and bring the attached QR image for check-in.</p>

    <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; border: 1px solid #d1d5db;">
        <tr>
            <td style="border: 1px solid #d1d5db;"><strong>Ticket</strong></td>
            <td style="border: 1px solid #d1d5db;">{{ $ticket->formatted_number ?: $ticket->ticket_number }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db;"><strong>Attendee</strong></td>
            <td style="border: 1px solid #d1d5db;">{{ trim(($attendee?->first_name ?? '') . ' ' . ($attendee?->last_name ?? '')) ?: 'Guest' }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db;"><strong>Ticket Type</strong></td>
            <td style="border: 1px solid #d1d5db;">{{ $ticket->ticketType->name }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #d1d5db;"><strong>Status</strong></td>
            <td style="border: 1px solid #d1d5db;">{{ str_replace('_', ' ', $ticket->status->value) }}</td>
        </tr>
    </table>

    @if($event->venue_name)
        <p><strong>Venue:</strong> {{ $event->venue_name }}</p>
    @endif

    <p style="color: #6b7280;">
        The QR image is the scannable ticket for fast entry. PDF and calendar files may also be attached when available.
    </p>
</body>
</html>
