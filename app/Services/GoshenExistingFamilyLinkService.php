<?php

namespace App\Services;

use App\Models\GoshenFamily;
use App\Models\GoshenFamilyMember;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAuditLog;
use Personal\EventInstallments\Models\Ticket;

class GoshenExistingFamilyLinkService
{
    /**
     * @param  array{father_ticket_id: int|string, mother_ticket_id: int|string, children?: array<int, array{ticket_id: int|string}>}  $data
     */
    public function link(User $admin, Event $event, string $name, string $reason, array $data): GoshenFamily
    {
        $name = trim($name);
        $reason = trim($reason);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['family_name' => 'Enter a family name of up to 120 characters.']);
        }
        if (mb_strlen($reason) < 12) {
            throw ValidationException::withMessages(['reason' => 'Enter an audit reason of at least 12 characters.']);
        }

        $roles = [
            'father' => $data['father_ticket_id'] ?? null,
            'mother' => $data['mother_ticket_id'] ?? null,
        ];
        foreach ($data['children'] ?? [] as $child) {
            $roles['child'][] = $child['ticket_id'] ?? null;
        }

        $ticketIds = collect($roles)
            ->flatten()
            ->filter(fn ($ticketId): bool => filled($ticketId))
            ->map(fn ($ticketId): int => (int) $ticketId)
            ->values();

        if ($ticketIds->count() < 2 || $ticketIds->count() !== $ticketIds->unique()->count()) {
            throw ValidationException::withMessages(['family' => 'Select a different existing ticket for every family member.']);
        }

        return DB::transaction(function () use ($admin, $event, $name, $reason, $roles, $ticketIds): GoshenFamily {
            $tickets = Ticket::query()
                ->with(['attendee', 'booking'])
                ->where('event_id', $event->id)
                ->whereIn('id', $ticketIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($tickets->count() !== $ticketIds->count()) {
                throw ValidationException::withMessages(['family' => 'Every selected ticket must belong to this retreat edition.']);
            }

            $attendeeIds = $tickets->pluck('attendee_id')->filter()->values();
            if ($attendeeIds->count() !== $ticketIds->count() || GoshenFamilyMember::query()->whereIn('attendee_id', $attendeeIds)->exists()) {
                throw ValidationException::withMessages(['family' => 'One or more selected attendees are already linked to a family.']);
            }

            $tickets->each(function (Ticket $ticket): void {
                if (! in_array($ticket->status, [TicketStatus::NotCheckedIn, TicketStatus::CheckedIn], true)
                    || $ticket->booking?->status !== BookingStatus::Paid) {
                    throw ValidationException::withMessages(['family' => 'Every selected ticket must be active and fully paid.']);
                }
            });

            $family = GoshenFamily::query()->create([
                'event_id' => $event->id,
                'booking_id' => null,
                'name' => $name,
            ]);

            $members = collect($roles)
                ->flatMap(function (mixed $ticketIdsForRole, string $role) use ($tickets): array {
                    return collect((array) $ticketIdsForRole)
                        ->filter(fn ($ticketId): bool => filled($ticketId))
                        ->map(fn ($ticketId): array => ['role' => $role, 'ticket' => $tickets->get((int) $ticketId)])
                        ->all();
                });

            $members->each(function (array $member) use ($family): void {
                /** @var Ticket $ticket */
                $ticket = $member['ticket'];
                /** @var Attendee $attendee */
                $attendee = $ticket->attendee;

                GoshenFamilyMember::query()->create([
                    'goshen_family_id' => $family->id,
                    'attendee_id' => $attendee->id,
                    'mobile_user_id' => $this->matchingMobileUser($attendee)?->id,
                    'role' => $member['role'],
                    'first_name' => $attendee->first_name,
                    'last_name' => $attendee->last_name,
                    'email' => $attendee->email,
                    'phone' => $attendee->phone,
                    'metadata' => ['linked_ticket_id' => $ticket->id],
                ]);
            });

            EventAuditLog::query()->create([
                'event_id' => $event->id,
                'actor_id' => $admin->id,
                'action' => 'goshen_existing_family_linked',
                'auditable_type' => $family::class,
                'auditable_id' => $family->id,
                'after' => [
                    'family_name' => $family->name,
                    'ticket_ids' => $ticketIds->all(),
                    'attendee_ids' => $attendeeIds->all(),
                    'booking_ids' => $tickets->pluck('booking_id')->unique()->values()->all(),
                ],
                'metadata' => ['reason' => $reason, 'source' => 'admin_existing_ticket_link'],
            ]);

            return $family->fresh('members');
        });
    }

    public function unlink(User $admin, GoshenFamily $family, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 12) {
            throw ValidationException::withMessages(['reason' => 'Enter an audit reason of at least 12 characters.']);
        }

        DB::transaction(function () use ($admin, $family, $reason): void {
            $family->loadMissing('members');
            $before = [
                'family_name' => $family->name,
                'ticket_ids' => $family->members->pluck('metadata.linked_ticket_id')->filter()->values()->all(),
                'attendee_ids' => $family->members->pluck('attendee_id')->filter()->values()->all(),
            ];

            $familyId = $family->id;
            $eventId = $family->event_id;
            $family->delete();

            EventAuditLog::query()->create([
                'event_id' => $eventId,
                'actor_id' => $admin->id,
                'action' => 'goshen_existing_family_unlinked',
                'auditable_type' => GoshenFamily::class,
                'auditable_id' => $familyId,
                'before' => $before,
                'metadata' => ['reason' => $reason, 'source' => 'admin_existing_ticket_unlink'],
            ]);
        });
    }

    private function matchingMobileUser(Attendee $attendee): ?MobileUser
    {
        $matches = MobileUser::query()
            ->where('is_deleted', false)
            ->where(function ($query) use ($attendee): void {
                if (filled($attendee->email)) {
                    $query->whereRaw('LOWER(email) = ?', [strtolower((string) $attendee->email)]);
                }
                if (filled($attendee->phone)) {
                    $query->{filled($attendee->email) ? 'orWhereRaw' : 'whereRaw'}(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '') = ?",
                        [preg_replace('/\D+/', '', (string) $attendee->phone)],
                    );
                }
            })
            ->lockForUpdate()
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
