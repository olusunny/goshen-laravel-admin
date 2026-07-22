<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Middleware;

use ChurchTools\GoshenPrayerAttendance\Services\AddonAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrayerAttendanceActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(AddonAvailability::class)->isActive()) {
            return response()->json([
                'data' => [
                    'code' => 'feature_unavailable',
                    'message' => 'Prayer Session Attendance is not available right now.',
                ],
            ], 404);
        }

        return $next($request);
    }
}
