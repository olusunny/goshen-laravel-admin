<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Http\Middleware;

use ChurchTools\ChurchBirthdayCelebrations\Services\AddonAvailability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBirthdayCelebrationsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(AddonAvailability::class)->isActive()) {
            return response()->json([
                'status' => 'error',
                'code' => 'ADDON_INACTIVE',
                'message' => 'Church Birthday Celebration is not available right now.',
            ], 404);
        }
        return $next($request);
    }
}
