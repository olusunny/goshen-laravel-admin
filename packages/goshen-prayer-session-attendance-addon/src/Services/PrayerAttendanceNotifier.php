<?php

namespace ChurchTools\GoshenPrayerAttendance\Services;

use App\Models\InboxMessage;
use App\Services\InboxMessageDeliveryService;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceConfirmation;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceDelivery;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use Illuminate\Support\Facades\DB;

class PrayerAttendanceNotifier
{
    public function __construct(
        private readonly AddonAvailability $availability,
    ) {}

    public function dispatch(PrayerSession $session, string $kind): void
    {
        if (! $this->availability->isActive() || ! $session->isActive()) {
            return;
        }

        $ticketClass = config('prayer-attendance.models.ticket');
        $confirmedTicketIds = PrayerAttendanceConfirmation::query()
            ->where('prayer_session_id', $session->getKey())
            ->whereNull('voided_at')
            ->pluck('ticket_id');

        $tickets = $ticketClass::query()
            ->with('booking')
            ->where('event_id', $session->event_id)
            ->whereNotIn('status', ['cancelled', 'unpaid', 'provisional'])
            ->when($kind === PrayerAttendanceDelivery::KIND_REMINDER, fn ($query) => $query->whereNotIn('id', $confirmedTicketIds))
            ->get();

        $recipientIds = [];
        foreach ($tickets as $ticket) {
            $userId = (int) ($ticket->booking?->customer_id ?: 0) ?: null;
            $claimed = $this->claim($session, (int) $ticket->getKey(), $userId, $kind);
            if ($claimed && $userId) {
                $recipientIds[] = $userId;
            }
        }

        $recipientIds = array_values(array_unique($recipientIds));
        if ($recipientIds === [] || ! class_exists(InboxMessage::class) || ! app()->bound(InboxMessageDeliveryService::class)) {
            return;
        }

        $message = InboxMessage::query()->create([
            'title' => config("prayer-attendance.notification.{$kind}_title"),
            'content' => config("prayer-attendance.notification.{$kind}_body"),
            'recipient_mode' => 'selected',
            'selected_mobile_user_ids' => $recipientIds,
            'notification_category' => config('prayer-attendance.notification.category', 'events'),
            'send_inbox' => true,
            'send_push' => true,
            'send_email' => false,
            'is_published' => true,
            'source' => InboxMessage::SOURCE_SYSTEM,
            'published_at' => now(),
        ]);

        app(InboxMessageDeliveryService::class)->dispatch($message);

        PrayerAttendanceDelivery::query()
            ->where('prayer_session_id', $session->getKey())
            ->where('kind', $kind)
            ->whereNull('sent_at')
            ->update(['sent_at' => now(), 'inbox_message_id' => (string) $message->getKey(), 'updated_at' => now()]);

        if ($kind === PrayerAttendanceDelivery::KIND_ACTIVATION) {
            $session->forceFill(['activation_notification_dispatched_at' => now()])->saveQuietly();
        }
    }

    private function claim(PrayerSession $session, int $ticketId, ?int $mobileUserId, string $kind): bool
    {
        try {
            return DB::transaction(function () use ($session, $ticketId, $mobileUserId, $kind): bool {
                $existing = PrayerAttendanceDelivery::query()
                    ->where('prayer_session_id', $session->getKey())
                    ->where('ticket_id', $ticketId)
                    ->where('kind', $kind)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return false;
                }

                PrayerAttendanceDelivery::query()->create([
                    'prayer_session_id' => $session->getKey(),
                    'ticket_id' => $ticketId,
                    'mobile_user_id' => $mobileUserId,
                    'kind' => $kind,
                    'claimed_at' => now(),
                ]);

                return true;
            });
        } catch (\Throwable) {
            return false;
        }
    }
}
