<?php

namespace App\Http\Middleware;

use App\Models\MobileUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->input('data', $request->all());
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        $token = trim((string) (($payload['api_token'] ?? null) ?: $request->bearerToken()));
        $user = $token === '' ? null : MobileUser::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        if (! $user instanceof MobileUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user->markApiSeen();
        $request->setUserResolver(fn (): MobileUser => $user);

        return $next($request);
    }
}
