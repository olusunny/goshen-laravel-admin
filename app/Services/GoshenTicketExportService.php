<?php

namespace App\Services;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Personal\EventInstallments\Models\EventAttendeeField;
use Personal\EventInstallments\Models\Ticket;

class GoshenTicketExportService
{
    public function __construct(
        private readonly GoshenBookingExportService $bookingExporter,
    ) {}

    /**
     * @return array<int, string>
     */
    public function headings(?Collection $registrationFields = null): array
    {
        $registrationFields ??= $this->bookingExporter->registrationFields();

        return array_merge([
            'Ticket database ID',
            'Ticket public ID',
            'Formatted ticket number',
            'Raw ticket number',
            'Ticket status',
            'QR hash',
            'Multiday status',
            'Ticket metadata',
            'Ticket issued at',
            'Ticket expires at',
            'Ticket created at',
            'Ticket updated at',
            'Retreat edition',
            'Retreat venue',
            'Retreat address',
            'Ticket type',
            'Ticket type SKU',
            'Ticket currency',
            'Ticket price',
            'Booking database ID',
            'Booking reference',
            'Booking status',
            'Booking customer name',
            'Booking customer email',
            'Booking customer phone',
            'Booking total',
            'Booking paid total',
            'Attendee database ID',
            'Attendee reference',
            'Attendee first name',
            'Attendee last name',
            'Attendee full name',
            'Attendee email',
            'Attendee phone',
            'Attendee company',
            'Attendee designation',
        ], $registrationFields
            ->map(fn (EventAttendeeField $field): string => 'Registration: '.$this->fieldLabel($field))
            ->all(), [
                'Additional attendee custom fields',
                'Check-in count',
                'Last check-in status',
                'Last check-in day',
                'Last checked in at',
                'Last check-in source',
                'Last ticket email status',
                'Last ticket email recipient',
                'Last ticket email sent at',
                'Last ticket email error',
                'PDF document path',
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    public function rowForTicket(Ticket $ticket, ?Collection $registrationFields = null): array
    {
        $registrationFields ??= $this->bookingExporter->registrationFields();

        $ticket->loadMissing([
            'event.attendeeFields',
            'booking',
            'attendee.ticketType',
            'ticketType',
            'checkIns',
            'emailLogs',
            'documents',
        ]);

        $attendee = $ticket->attendee;
        $booking = $ticket->booking;
        $event = $ticket->event;
        $ticketType = $ticket->ticketType ?: $attendee?->ticketType;
        $eventFields = $event?->attendeeFields?->keyBy('key') ?? collect();
        $customFields = is_array($attendee?->custom_fields) ? $attendee->custom_fields : [];
        $ticketStatus = $ticket->status instanceof BackedEnum ? $ticket->status->value : $ticket->status;
        $bookingStatus = $booking?->status instanceof BackedEnum ? $booking->status->value : $booking?->status;
        $lastCheckIn = $ticket->checkIns->sortByDesc('id')->first();
        $lastEmail = $ticket->emailLogs->sortByDesc('id')->first();
        $pdfDocument = $ticket->documents->firstWhere('type', 'pdf');

        $registeredFieldValues = $registrationFields
            ->map(function (EventAttendeeField $field) use ($attendee, $eventFields): string {
                $eventField = $eventFields->get($field->key);

                return $this->bookingExporter->attendeeFieldValue(
                    $attendee,
                    (string) $field->key,
                    $eventField instanceof EventAttendeeField ? $eventField : $field,
                );
            })
            ->all();

        $knownKeys = $registrationFields
            ->pluck('key')
            ->map(fn ($key): string => (string) $key)
            ->all();

        $additionalCustomFields = collect($customFields)
            ->reject(fn (mixed $value, string $key): bool => in_array($key, $knownKeys, true))
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->all();

        return array_merge([
            $ticket->id,
            $ticket->public_id,
            $ticket->formatted_number,
            $ticket->ticket_number,
            $ticketStatus,
            $ticket->qr_hash,
            $this->jsonValue($ticket->multiday_status),
            $this->jsonValue($ticket->metadata),
            $ticket->issued_at?->toDateTimeString(),
            $ticket->expires_at?->toDateTimeString(),
            $ticket->created_at?->toDateTimeString(),
            $ticket->updated_at?->toDateTimeString(),
            $event?->name,
            $event?->venue_name,
            $event?->venue_address,
            $ticketType?->name,
            $ticketType?->sku,
            $ticketType?->currency,
            $ticketType?->price,
            $booking?->id,
            $booking?->public_id,
            $bookingStatus,
            $booking?->customer_name,
            $booking?->customer_email,
            $booking?->customer_phone,
            $booking?->total,
            $booking?->paid_total,
            $attendee?->id,
            $attendee?->public_id,
            $attendee?->first_name,
            $attendee?->last_name,
            trim((string) ($attendee?->first_name).' '.(string) ($attendee?->last_name)),
            $attendee?->email,
            $attendee?->phone,
            $attendee?->company,
            $attendee?->designation,
        ], $registeredFieldValues, [
            $additionalCustomFields === [] ? '' : $this->jsonValue($additionalCustomFields),
            $ticket->checkIns->count(),
            $lastCheckIn?->status instanceof BackedEnum ? $lastCheckIn->status->value : $lastCheckIn?->status,
            $lastCheckIn?->day_number,
            $lastCheckIn?->checked_in_at?->toDateTimeString(),
            $lastCheckIn?->source,
            $lastEmail?->status,
            $lastEmail?->recipient,
            $lastEmail?->sent_at?->toDateTimeString(),
            $lastEmail?->error,
            $pdfDocument?->path,
        ]);
    }

    public function writeCsv(Builder $query, mixed $output): void
    {
        $registrationFields = $this->bookingExporter->registrationFields();

        fputcsv($output, $this->headings($registrationFields));

        $query
            ->with([
                'event.attendeeFields',
                'booking',
                'attendee.ticketType',
                'ticketType',
                'checkIns',
                'emailLogs',
                'documents',
            ])
            ->chunk(200, function (Collection $tickets) use ($output, $registrationFields): void {
                foreach ($tickets as $ticket) {
                    if (! $ticket instanceof Ticket) {
                        continue;
                    }

                    fputcsv($output, $this->rowForTicket($ticket, $registrationFields));
                }
            });
    }

    private function fieldLabel(EventAttendeeField $field): string
    {
        $label = trim((string) $field->label);

        return $label !== '' ? $label : (string) $field->key;
    }

    private function jsonValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
