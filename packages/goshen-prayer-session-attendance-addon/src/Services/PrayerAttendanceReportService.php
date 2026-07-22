<?php

namespace ChurchTools\GoshenPrayerAttendance\Services;

use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceConfirmation;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use Illuminate\Database\Eloquent\Model;
use Personal\EventInstallments\Enums\TicketStatus;

class PrayerAttendanceReportService
{
    public function __construct(private readonly PrayerAttendanceService $attendance) {}

    /** @return array{eligible:int,confirmed:int,not_confirmed:int,total:int,confirmation_rate:float} */
    public function sessionSummary(PrayerSession $session): array
    {
        return $this->attendance->metrics($session);
    }

    /** @param array<int, array<string, mixed>> $rows @return array{confirmed:int,not_confirmed:int,total:int,confirmation_rate:float} */
    public function summaryForRows(array $rows): array
    {
        $total = count($rows);
        $confirmed = collect($rows)->where('status', 'Confirmed')->count();

        return [
            'confirmed' => $confirmed,
            'not_confirmed' => $total - $confirmed,
            'total' => $total,
            'confirmation_rate' => $total ? round($confirmed / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Normalize the query parameters shared by the JSON report and CSV export.
     *
     * @param array<string, mixed> $input
     * @return array{status:string,gender:?string,age_group:?string,residence:?string,repeated:?bool}
     */
    public function filters(array $input): array
    {
        $status = strtolower(trim((string) ($input['status'] ?? 'all')));
        $status = in_array($status, ['all', 'confirmed', 'not_confirmed'], true) ? $status : 'all';
        $repeated = strtolower(trim((string) ($input['repeated'] ?? '')));

        return [
            'status' => $status,
            'gender' => $this->filterValue($input['gender'] ?? null),
            'age_group' => $this->filterValue($input['age_group'] ?? null),
            'residence' => $this->filterValue($input['residence'] ?? null),
            'repeated' => match ($repeated) {
                'yes', 'true', '1' => true,
                'no', 'false', '0' => false,
                default => null,
            },
        ];
    }

    /**
     * Every eligible ticket receives exactly one current-session row. This keeps
     * "Not Confirmed" descriptive: it means no non-voided record exists.
     *
     * @param array{status?:string,gender?:?string,age_group?:?string,residence?:?string,repeated?:?bool} $filters
     * @return array<int, array<string, mixed>>
     */
    public function rows(PrayerSession $session, array $filters = []): array
    {
        $filters = array_replace($this->filters([]), $filters);
        $ticketClass = config('prayer-attendance.models.ticket');
        $tickets = $ticketClass::query()
            ->where('event_id', $session->event_id)
            ->whereNotIn('status', [
                TicketStatus::Cancelled->value,
                TicketStatus::Unpaid->value,
                TicketStatus::Provisional->value,
            ])
            ->with(['attendee', 'booking'])
            ->orderBy('id')
            ->get();

        $confirmations = PrayerAttendanceConfirmation::query()
            ->where('prayer_session_id', $session->getKey())
            ->whereNull('voided_at')
            ->get()
            ->keyBy('ticket_id');
        $history = $this->attendanceHistory($session, $tickets->pluck('id')->all());
        $members = $this->membersFor($tickets);
        $allocations = $this->allocationsFor($session, $tickets->pluck('attendee_id')->filter()->all());

        return $tickets
            ->map(function (Model $ticket) use ($confirmations, $history, $members, $allocations): array {
                $confirmation = $confirmations->get($ticket->getKey());
                $attendee = $ticket->attendee;
                $member = $this->memberForTicket($ticket, $members);
                $custom = is_array($attendee?->custom_fields) ? $attendee->custom_fields : [];
                $attendeeId = $ticket->attendee_id ? (int) $ticket->attendee_id : null;
                $historyForTicket = $history[(int) $ticket->getKey()] ?? [];
                $confirmedSessions = count($historyForTicket);
                $residence = $attendeeId ? ($allocations[$attendeeId] ?? 'Unassigned') : 'Unassigned';

                return [
                    'status' => $confirmation ? 'Confirmed' : 'Not Confirmed',
                    'ticket_id' => (string) ($ticket->formatted_number ?: $ticket->ticket_number ?: $ticket->public_id),
                    'ticket_public_id' => $ticket->public_id,
                    'attendee' => trim((string) (($attendee?->first_name ?? '').' '.($attendee?->last_name ?? ''))),
                    'gender' => $this->label($custom['gender'] ?? $member?->gender, 'Unspecified'),
                    'age_group' => $this->label($custom['age_group'] ?? null, 'Unspecified'),
                    'residence' => $residence,
                    'method' => $confirmation?->method,
                    'confirmed_at' => $confirmation?->confirmed_at?->toIso8601String(),
                    'confirmed_sessions' => $confirmedSessions,
                    'attendance_pattern' => $confirmedSessions > 1
                        ? "Repeated confirmation ({$confirmedSessions} sessions)"
                        : ($confirmedSessions === 1 ? 'One confirmed session' : 'No confirmed sessions yet'),
                    'attendance_history' => collect($historyForTicket)
                        ->map(fn (array $entry): string => $entry['session'].' - '.$entry['confirmed_at'])
                        ->implode('; '),
                ];
            })
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['status'] !== 'all' && strtolower(str_replace(' ', '_', $row['status'])) !== $filters['status']) {
                    return false;
                }

                foreach (['gender', 'age_group', 'residence'] as $filter) {
                    if ($filters[$filter] !== null && strcasecmp((string) $row[$filter], $filters[$filter]) !== 0) {
                        return false;
                    }
                }

                return $filters['repeated'] === null
                    || ($filters['repeated'] === ((int) $row['confirmed_sessions'] > 1));
            })
            ->values()
            ->all();
    }

    /** @param array<int, int> $ticketIds @return array<int, array<int, array{session:string,confirmed_at:string}>> */
    private function attendanceHistory(PrayerSession $session, array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        return PrayerAttendanceConfirmation::query()
            ->whereNull('voided_at')
            ->whereIn('ticket_id', $ticketIds)
            ->whereHas('session', fn ($query) => $query->where('event_id', $session->event_id))
            ->with('session:id,name')
            ->orderBy('confirmed_at')
            ->get()
            ->groupBy('ticket_id')
            ->map(fn ($entries): array => $entries->map(fn (PrayerAttendanceConfirmation $entry): array => [
                'session' => (string) ($entry->session?->name ?? 'Prayer session'),
                'confirmed_at' => (string) $entry->confirmed_at?->toIso8601String(),
            ])->all())
            ->all();
    }

    /** @return array{by_id: array<int, Model>, by_email: array<string, Model>} */
    private function membersFor($tickets): array
    {
        $model = config('prayer-attendance.models.mobile_user');
        if (! is_string($model) || ! class_exists($model)) {
            return ['by_id' => [], 'by_email' => []];
        }

        $customerIds = $tickets->pluck('booking.customer_id')->filter()->unique()->values()->all();
        $emails = $tickets->pluck('booking.customer_email')->filter()->map(fn ($email) => strtolower((string) $email))->unique()->values()->all();
        $members = $model::query()
            ->where(function ($query) use ($customerIds, $emails): void {
                if ($customerIds !== []) {
                    $query->whereIn('id', $customerIds);
                }
                if ($emails !== []) {
                    $customerIds === [] ? $query->whereIn('email', $emails) : $query->orWhereIn('email', $emails);
                }
            })
            ->get();

        return [
            'by_id' => $members->keyBy('id')->all(),
            'by_email' => $members->filter(fn (Model $member) => filled($member->getAttribute('email')))
                ->keyBy(fn (Model $member) => strtolower((string) $member->getAttribute('email')))
                ->all(),
        ];
    }

    /** @param array{by_id: array<int, Model>, by_email: array<string, Model>} $members */
    private function memberForTicket(Model $ticket, array $members): ?Model
    {
        $booking = $ticket->booking;
        $customerId = $booking?->customer_id;
        if ($customerId && isset($members['by_id'][(int) $customerId])) {
            return $members['by_id'][(int) $customerId];
        }

        $email = strtolower(trim((string) ($booking?->customer_email ?? $ticket->attendee?->email)));

        return $email !== '' ? ($members['by_email'][$email] ?? null) : null;
    }

    /** @param array<int, int> $attendeeIds @return array<int, string> */
    private function allocationsFor(PrayerSession $session, array $attendeeIds): array
    {
        $model = 'App\\Models\\GoshenAccommodationAllocation';
        if ($attendeeIds === [] || ! class_exists($model)) {
            return [];
        }

        return $model::query()
            ->where('event_id', $session->event_id)
            ->whereIn('attendee_id', $attendeeIds)
            ->get()
            ->mapWithKeys(function (Model $allocation): array {
                $residence = implode(' / ', array_filter([
                    $allocation->getAttribute('building'),
                    $allocation->getAttribute('room'),
                    $allocation->getAttribute('bed'),
                ], fn ($value) => filled($value)));

                return [(int) $allocation->getAttribute('attendee_id') => $residence !== '' ? $residence : 'Unassigned'];
            })
            ->all();
    }

    private function filterValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && mb_strlen($value) <= 120 ? $value : null;
    }

    private function label(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }
}
