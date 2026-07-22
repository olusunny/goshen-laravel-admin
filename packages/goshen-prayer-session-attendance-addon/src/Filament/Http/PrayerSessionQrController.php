<?php

namespace ChurchTools\GoshenPrayerAttendance\Filament\Http;

use ChurchTools\GoshenPrayerAttendance\Filament\Resources\PrayerSessionResource;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerSessionAttendanceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrayerSessionQrController
{
    public function __invoke(Request $request, PrayerSession $session, PrayerSessionAttendanceService $attendance): Response
    {
        abort_unless(PrayerSessionResource::canViewPrayerAttendanceQr(), 404);
        abort_unless($session->status === 'active', 404);

        $response = $attendance->adminQrResponse(
            $session,
            $request->boolean('download'),
            $request->user(),
        );

        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
