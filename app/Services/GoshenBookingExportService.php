<?php

namespace App\Services;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\EventAttendeeField;

class GoshenBookingExportService
{
    public function __construct(
        private readonly GoshenRegistrationFieldService $registrationFields,
    ) {}

    /**
     * @return Collection<int, EventAttendeeField>
     */
    public function registrationFields(): Collection
    {
        if (! Schema::hasTable('ei_event_attendee_fields')) {
            return collect();
        }

        return EventAttendeeField::query()
            ->whereHas('event', fn (Builder $query) => self::applyGoshenEventScope($query))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->unique(fn (EventAttendeeField $field): string => (string) $field->key)
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function headings(?Collection $registrationFields = null): array
    {
        $registrationFields ??= $this->registrationFields();

        return array_merge([
            'Booking database ID',
            'Booking reference',
            'Booking status',
            'Retreat edition',
            'Customer name',
            'Customer email',
            'Customer phone',
            'Currency',
            'Subtotal',
            'Total',
            'Paid total',
            'Booking created at',
            'Booking updated at',
            'Attendee number',
            'Attendee database ID',
            'Attendee reference',
            'Ticket number',
            'Ticket status',
            'Ticket type',
            'First name',
            'Last name',
            'Full name',
            'Attendee email',
            'Attendee phone',
            'Company',
            'Designation',
        ], $registrationFields
            ->map(fn (EventAttendeeField $field): string => 'Registration: '.$this->fieldLabel($field))
            ->all(), [
                'Additional custom fields',
            ]);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function rowsForBooking(Booking $booking, ?Collection $registrationFields = null): array
    {
        $registrationFields ??= $this->registrationFields();

        $booking->loadMissing([
            'event.attendeeFields',
            'attendees.ticket',
            'attendees.ticketType',
        ]);

        if ($booking->attendees->isEmpty()) {
            return [$this->rowFor($booking, null, 0, $registrationFields)];
        }

        return $booking->attendees
            ->values()
            ->map(fn (Attendee $attendee, int $index): array => $this->rowFor($booking, $attendee, $index, $registrationFields))
            ->all();
    }

    public function writeCsv(Builder $query, mixed $output): void
    {
        $registrationFields = $this->registrationFields();

        fputcsv($output, $this->headings($registrationFields));

        $query
            ->with([
                'event.attendeeFields',
                'attendees.ticket',
                'attendees.ticketType',
            ])
            ->chunk(200, function (Collection $bookings) use ($output, $registrationFields): void {
                foreach ($bookings as $booking) {
                    if (! $booking instanceof Booking) {
                        continue;
                    }

                    foreach ($this->rowsForBooking($booking, $registrationFields) as $row) {
                        fputcsv($output, $row);
                    }
                }
            });
    }

    public static function applyGoshenEventScope(Builder $query): Builder
    {
        return $query
            ->where('settings->module', 'goshen_retreat')
            ->orWhere('settings->module', 'goshen-retreat')
            ->orWhere('settings->app_module', 'goshen_retreat')
            ->orWhere('slug', 'like', 'goshen-retreat%')
            ->orWhere('slug', 'like', 'goshen-%')
            ->orWhere('name', 'like', '%Goshen Retreat%');
    }

    public function attendeeFieldValue(?Attendee $attendee, string $key, ?EventAttendeeField $field = null): string
    {
        if (! $attendee instanceof Attendee) {
            return '';
        }

        $raw = match ($key) {
            'first_name' => $attendee->first_name,
            'last_name' => $attendee->last_name,
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'company' => $attendee->company,
            'designation' => $attendee->designation,
            default => data_get(is_array($attendee->custom_fields) ? $attendee->custom_fields : [], $key),
        };

        if ($raw === null || $raw === '') {
            return '';
        }

        if ($field instanceof EventAttendeeField && is_array($field->options) && $field->options !== []) {
            return $this->registrationFields->optionLabel($field, (string) $raw);
        }

        return is_scalar($raw) ? (string) $raw : json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function rowFor(Booking $booking, ?Attendee $attendee, int $index, Collection $registrationFields): array
    {
        $ticket = $attendee?->ticket;
        $ticketStatus = $ticket?->status instanceof BackedEnum ? $ticket->status->value : $ticket?->status;
        $bookingStatus = $booking->status instanceof BackedEnum ? $booking->status->value : $booking->status;
        $eventFields = $booking->event?->attendeeFields?->keyBy('key') ?? collect();
        $customFields = is_array($attendee?->custom_fields) ? $attendee->custom_fields : [];

        $registeredFieldValues = $registrationFields
            ->map(function (EventAttendeeField $field) use ($attendee, $eventFields): string {
                $eventField = $eventFields->get($field->key);

                return $this->attendeeFieldValue($attendee, (string) $field->key, $eventField instanceof EventAttendeeField ? $eventField : $field);
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
            $booking->id,
            $booking->public_id,
            $bookingStatus,
            $booking->event?->name,
            $booking->customer_name,
            $booking->customer_email,
            $booking->customer_phone,
            $booking->currency,
            $booking->subtotal,
            $booking->total,
            $booking->paid_total,
            $booking->created_at?->toDateTimeString(),
            $booking->updated_at?->toDateTimeString(),
            $attendee ? $index + 1 : '',
            $attendee?->id,
            $attendee?->public_id,
            $ticket?->formatted_number ?: $ticket?->ticket_number,
            $ticketStatus,
            $attendee?->ticketType?->name,
            $attendee?->first_name,
            $attendee?->last_name,
            trim((string) ($attendee?->first_name).' '.(string) ($attendee?->last_name)),
            $attendee?->email,
            $attendee?->phone,
            $attendee?->company,
            $attendee?->designation,
        ], $registeredFieldValues, [
            $additionalCustomFields === [] ? '' : json_encode($additionalCustomFields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function fieldLabel(EventAttendeeField $field): string
    {
        $label = trim((string) $field->label);

        return $label !== '' ? $label : (string) $field->key;
    }
}
