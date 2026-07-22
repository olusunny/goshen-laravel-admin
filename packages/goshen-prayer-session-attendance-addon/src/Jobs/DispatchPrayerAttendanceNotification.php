<?php

namespace ChurchTools\GoshenPrayerAttendance\Jobs;

use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceDelivery;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use ChurchTools\GoshenPrayerAttendance\Services\AddonAvailability;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchPrayerAttendanceNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $sessionId,
        public readonly string $kind,
    ) {}

    public function handle(AddonAvailability $availability, PrayerAttendanceNotifier $notifier): void
    {
        if (! $availability->isActive() || ! in_array($this->kind, [PrayerAttendanceDelivery::KIND_ACTIVATION, PrayerAttendanceDelivery::KIND_REMINDER], true)) {
            return;
        }

        $session = PrayerSession::query()->find($this->sessionId);
        if (! $session instanceof PrayerSession || ! $session->isActive()) {
            return;
        }

        $notifier->dispatch($session, $this->kind);
    }
}
