<?php

namespace ChurchTools\DigitalCounseling\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCounselingRequester
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authenticatedUser($request);

        if (! $user instanceof Model) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (method_exists($user, 'markApiSeen')) {
            $user->markApiSeen();
        }

        $request->setUserResolver(function (?string $guard = null) use ($user) {
            $configuredGuard = (string) config('counseling.auth.guard', 'mobile');

            if ($guard === null || $guard === $configuredGuard || $guard === 'mobile') {
                return $user;
            }

            return Auth::guard($guard)->user();
        });

        Auth::shouldUse((string) config('counseling.auth.guard', 'mobile'));

        return $next($request);
    }

    private function authenticatedUser(Request $request): ?Model
    {
        $guard = (string) config('counseling.auth.guard', 'mobile');
        $modelClass = (string) config('counseling.models.requester');

        if (! is_a($modelClass, Model::class, true)) {
            return null;
        }

        $guardUser = $request->user($guard) ?? $request->user();
        if ($guardUser instanceof Model && $guardUser instanceof $modelClass) {
            return $guardUser;
        }

        $token = $request->bearerToken();
        $tokenInput = (string) config('counseling.auth.token_input', 'api_token');
        if (! $token && $tokenInput !== '') {
            $token = (string) $request->input($tokenInput, '');
        }

        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        $column = (string) config('counseling.auth.bearer_token_column', 'api_token_hash');
        if ($column === '') {
            return null;
        }

        $hashAlgorithm = (string) config('counseling.auth.bearer_token_hash', 'sha256');
        if ($hashAlgorithm !== '' && ! in_array($hashAlgorithm, hash_algos(), true)) {
            return null;
        }

        $lookupValue = $hashAlgorithm === ''
            ? $token
            : hash($hashAlgorithm, $token);

        /** @var Model|null $user */
        $user = $modelClass::query()
            ->where($column, $lookupValue)
            ->first();

        return $user;
    }
}
