<?php

namespace ChurchTools\GoshenPrayerAttendance\Services;

use ChurchTools\GoshenPrayerAttendance\Jobs\DispatchPrayerAttendanceNotification;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceAudit;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceConfirmation;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Enums\TicketStatus;

class PrayerAttendanceService
{
    public function __construct(private readonly PrayerSessionQrService $qr) {}

    public function activate(PrayerSession $session, Model $actor): PrayerSession
    {
        return DB::transaction(function () use ($session, $actor): PrayerSession {
            $locked = PrayerSession::query()->lockForUpdate()->findOrFail($session->getKey());
            if ($locked->isActive()) return $locked;
            abort_unless($locked->status === PrayerSession::STATUS_SCHEDULED, 422, 'Only scheduled sessions may be activated.');
            $isInitialActivation = $locked->activated_at === null;
            $locked->forceFill([
                'status' => PrayerSession::STATUS_ACTIVE,
                'activated_at' => $locked->activated_at ?? now(),
                'activated_by_mobile_user_id' => $locked->activated_by_mobile_user_id ?? $actor->getKey(),
                'closed_at' => null,
                'closed_by_mobile_user_id' => null,
            ])->save();
            $this->qr->activate($locked);
            $this->audit($locked, $actor, 'activated');
            if ($isInitialActivation) {
                DB::afterCommit(fn () => DispatchPrayerAttendanceNotification::dispatch((int) $locked->getKey(), 'activation'));
            }
            return $locked->fresh();
        });
    }

    public function close(PrayerSession $session, Model $actor): PrayerSession
    {
        return DB::transaction(function () use ($session, $actor): PrayerSession {
            $locked = PrayerSession::query()->lockForUpdate()->findOrFail($session->getKey());
            abort_unless($locked->isActive(), 422, 'Only active sessions may be closed.');
            $locked->forceFill(['status' => PrayerSession::STATUS_CLOSED, 'closed_at' => now(), 'closed_by_mobile_user_id' => $actor->getKey(), 'qr_generation_id' => null, 'qr_token_hash' => null, 'qr_activated_at' => null])->save();
            $this->audit($locked, $actor, 'closed');
            return $locked->fresh();
        });
    }

    public function reopen(PrayerSession $session, Model $actor, string $reason): PrayerSession
    {
        return DB::transaction(function () use ($session, $actor, $reason): PrayerSession {
            $locked = PrayerSession::query()->lockForUpdate()->findOrFail($session->getKey());
            abort_unless($locked->status === PrayerSession::STATUS_CLOSED, 422, 'Only closed sessions may be reopened.');
            $locked->forceFill(['status' => PrayerSession::STATUS_SCHEDULED, 'closed_at' => null, 'closed_by_mobile_user_id' => null, 'last_reopen_reason' => $reason, 'reopened_count' => (int) $locked->reopened_count + 1])->save();
            $this->audit($locked, $actor, 'reopened', ['reason' => $reason]);
            return $locked->fresh();
        });
    }

    public function queueReminder(PrayerSession $session, Model $actor): PrayerSession
    {
        return DB::transaction(function () use ($session, $actor): PrayerSession {
            $locked = PrayerSession::query()->lockForUpdate()->findOrFail($session->getKey());
            abort_unless($locked->isActive(), 422, 'A reminder can only be sent while a session is active.');
            abort_if($locked->reminder_dispatched_at, 422, 'The gentle reminder has already been sent for this session.');
            $locked->forceFill(['reminder_dispatched_at' => now(), 'reminder_sent_by_mobile_user_id' => $actor->getKey()])->save();
            $this->audit($locked, $actor, 'reminder_queued');
            DB::afterCommit(fn () => DispatchPrayerAttendanceNotification::dispatch((int) $locked->getKey(), 'reminder'));
            return $locked->fresh();
        });
    }

    public function qrToken(PrayerSession $session): string { return $this->qr->token($session); }

    public function sessionForQrToken(string $token): PrayerSession
    {
        $parts = $this->qr->parse($token);
        $session = PrayerSession::query()->where('public_id', $parts['public_id'])->first();
        abort_unless($session instanceof PrayerSession && $this->qr->isCurrent($session, $token), 422, 'This prayer session QR code is invalid or no longer active.');
        return $session;
    }

    public function confirmSelf(string $token, Model $actor, ?string $identifier = null, ?string $idempotencyKey = null): PrayerAttendanceConfirmation
    {
        $session = $this->sessionForQrToken($token);
        $tickets = $this->selfEligibleTickets($session, $actor);
        $ticket = collect($tickets)->first(fn (Model $ticket) => $identifier !== null && in_array($identifier, [(string) $ticket->public_id, (string) $ticket->ticket_number, (string) $ticket->formatted_number], true));
        if (! $ticket && $identifier === null && count($tickets) === 1) $ticket = $tickets[0];
        if (! $ticket) throw (new ModelNotFoundException())->setModel(config('prayer-attendance.models.ticket'), [$identifier ?: 'eligible ticket']);
        return $this->confirm($session, $ticket, PrayerAttendanceConfirmation::METHOD_SELF_QR, null, $idempotencyKey, 'mobile_self');
    }

    public function confirmStaff(PrayerSession $session, string $identifier, Model $actor, string $method, ?string $idempotencyKey = null): PrayerAttendanceConfirmation
    {
        return $this->confirm($session, $this->findEventTicket($session, $identifier), $method, $actor, $idempotencyKey, 'mobile_staff');
    }

    public function findEventTicket(PrayerSession $session, string $identifier): Model
    {
        $class = config('prayer-attendance.models.ticket');
        $ticket = $class::query()->where('event_id', $session->event_id)->where(fn ($q) => $q->where('public_id', $identifier)->orWhere('ticket_number', $identifier)->orWhere('formatted_number', $identifier))->first();
        if (! $ticket instanceof Model || ! $this->ticketEligible($ticket)) throw (new ModelNotFoundException())->setModel($class, [$identifier]);
        return $ticket;
    }

    /** @return array<int, Model> */
    public function selfEligibleTickets(PrayerSession $session, Model $actor): array
    {
        $class = config('prayer-attendance.models.ticket');

        return $class::query()
            ->with('attendee')
            ->where('event_id', $session->event_id)
            ->get()
            ->filter(fn (Model $ticket) => $this->ticketEligible($ticket) && $this->actorOwnsOrHasDelegationForTicket($actor, $ticket))
            ->values()
            ->all();
    }

    /** @return array{confirmed:int,not_confirmed:int,total:int,eligible:int,confirmation_rate:float} */
    public function metrics(PrayerSession $session): array
    {
        $class = config('prayer-attendance.models.ticket');
        $total = $class::query()->where('event_id', $session->event_id)->whereNotIn('status', [TicketStatus::Cancelled->value, TicketStatus::Unpaid->value, TicketStatus::Provisional->value])->count();
        $confirmed = PrayerAttendanceConfirmation::query()->where('prayer_session_id', $session->getKey())->whereNull('voided_at')->count();
        return ['confirmed' => $confirmed, 'not_confirmed' => max(0, $total - $confirmed), 'total' => $total, 'eligible' => $total, 'confirmation_rate' => $total ? round($confirmed / $total * 100, 1) : 0.0];
    }

    public function escapeCsvCell(mixed $value): string { $value = str_replace(["\r", "\n"], ' ', trim((string) $value)); return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value; }

    private function confirm(PrayerSession $session, Model $ticket, string $method, ?Model $staff, ?string $key, string $source): PrayerAttendanceConfirmation
    {
        return DB::transaction(function () use ($session, $ticket, $method, $staff, $key, $source): PrayerAttendanceConfirmation {
            $locked = PrayerSession::query()->lockForUpdate()->findOrFail($session->getKey()); abort_unless($locked->isActive(), 422, 'This prayer session is not active.');
            $existing = PrayerAttendanceConfirmation::query()->where('prayer_session_id', $locked->getKey())->where('ticket_id', $ticket->getKey())->lockForUpdate()->first(); if ($existing) return $existing;
            if ($key && ($retry = PrayerAttendanceConfirmation::query()->where('prayer_session_id', $locked->getKey())->where('idempotency_key', $key)->lockForUpdate()->first())) {
                abort_unless((int) $retry->ticket_id === (int) $ticket->getKey(), 409, 'This offline confirmation key belongs to a different ticket.');

                return $retry;
            }
            $confirmation = PrayerAttendanceConfirmation::query()->create(['prayer_session_id' => $locked->getKey(), 'ticket_id' => $ticket->getKey(), 'attendee_id' => $ticket->attendee_id, 'method' => $method, 'recorded_by_mobile_user_id' => $staff?->getKey(), 'confirmed_at' => now(), 'idempotency_key' => $key, 'source' => $source, 'source_metadata' => []]);
            $this->audit($locked, $staff, 'confirmed', ['ticket_id' => $ticket->getKey(), 'method' => $method]); return $confirmation;
        }, 3);
    }

    private function ticketEligible(Model $ticket): bool
    {
        $status = $ticket->getAttribute('status');
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        return ! in_array($status, [TicketStatus::Cancelled->value, TicketStatus::Unpaid->value, TicketStatus::Provisional->value], true);
    }

    private function actorOwnsOrHasDelegationForTicket(Model $actor, Model $ticket): bool
    {
        $actorId = (string) $actor->getKey();
        $actorEmail = strtolower(trim((string) $actor->getAttribute('email')));
        $attendeeEmail = strtolower(trim((string) $ticket->attendee?->getAttribute('email')));

        if ($actorEmail !== '' && hash_equals($attendeeEmail, $actorEmail)) {
            return true;
        }

        $delegation = data_get($ticket->getAttribute('metadata'), 'prayer_attendance.self_confirmation_delegation');
        if (! is_array($delegation) || (string) ($delegation['mobile_user_id'] ?? '') !== $actorId) {
            return false;
        }

        $expiresAt = $delegation['expires_at'] ?? null;
        if ($expiresAt === null || $expiresAt === '') {
            return true;
        }
        if (! is_string($expiresAt)) {
            return false;
        }

        try {
            return CarbonImmutable::parse($expiresAt)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }
    private function audit(PrayerSession $session, ?Model $actor, string $action, array $metadata = []): void { PrayerAttendanceAudit::query()->create(['prayer_session_id' => $session->getKey(), 'actor_mobile_user_id' => $actor?->getKey(), 'action' => $action, 'metadata' => $metadata]); }
}
