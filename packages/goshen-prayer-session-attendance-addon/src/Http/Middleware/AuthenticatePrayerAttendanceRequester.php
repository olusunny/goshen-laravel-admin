<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePrayerAttendanceRequester
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->input('data', $request->all());
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }
        $token = trim((string) (($payload['api_token'] ?? null) ?: $request->bearerToken()));
        $model = config('prayer-attendance.models.mobile_user');
        $user = $token !== '' && is_a($model, Model::class, true)
            ? $model::query()->where('api_token_hash', hash('sha256', $token))->first()
            : null;

        if (! $user instanceof Model) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        if (method_exists($user, 'markApiSeen')) {
            $user->markApiSeen();
        }
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
