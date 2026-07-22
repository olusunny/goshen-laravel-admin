<?php

namespace ChurchTools\GoshenPrayerAttendance\Services;

use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceAudit;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceConfirmation;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class PrayerSessionAttendanceService
{
    public function __construct(
        private readonly PrayerAttendanceService $attendance,
        private readonly PrayerSessionQrService $qr,
    ) {}

    public function activate(PrayerSession $session, Model $actor): PrayerSession { return $this->attendance->activate($session, $actor); }
    public function close(PrayerSession $session, Model $actor): PrayerSession { return $this->attendance->close($session, $actor); }
    public function reopen(PrayerSession $session, Model $actor, string $reason): PrayerSession { return $this->attendance->reopen($session, $actor, $reason); }
    public function sendNotConfirmedReminder(PrayerSession $session, Model $actor): PrayerSession { return $this->attendance->queueReminder($session, $actor); }
    public function reminderPreview(PrayerSession $session): array { return ['recipient_count' => $this->attendance->metrics($session)['not_confirmed']]; }

    public function voidAttendance(PrayerSession $session, string $confirmationId, Model $actor, string $reason): PrayerAttendanceConfirmation
    {
        return DB::transaction(function () use ($session, $confirmationId, $actor, $reason): PrayerAttendanceConfirmation {
            if ($session->status !== PrayerSession::STATUS_CLOSED) throw new RuntimeException('Attendance corrections are available after the session is closed.');
            $confirmation = PrayerAttendanceConfirmation::query()->where('prayer_session_id', $session->getKey())->whereKey($confirmationId)->lockForUpdate()->firstOrFail();
            if ($confirmation->voided_at) return $confirmation;
            $confirmation->forceFill(['voided_at' => now(), 'voided_by_mobile_user_id' => $actor->getKey(), 'void_reason' => $reason])->save();
            PrayerAttendanceAudit::query()->create(['prayer_session_id' => $session->getKey(), 'actor_mobile_user_id' => $actor->getKey(), 'action' => 'confirmation_voided', 'metadata' => ['confirmation_id' => $confirmation->getKey(), 'reason' => $reason]]);
            return $confirmation;
        });
    }

    public function adminQrResponse(PrayerSession $session, bool $download, ?Model $actor = null): Response
    {
        $svg = $this->qr->renderSvg($session);
        $response = response($svg, 200, ['Content-Type' => 'image/svg+xml; charset=UTF-8']);
        if ($download) $response->headers->set('Content-Disposition', 'attachment; filename="prayer-session-'.$session->public_id.'.svg"');
        return $response;
    }
}
