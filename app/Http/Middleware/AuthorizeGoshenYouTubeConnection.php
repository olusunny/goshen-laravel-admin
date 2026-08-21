<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeGoshenYouTubeConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user && (
                $user->hasRole('super_admin', 'web')
                || $user->can(AdminPermissions::TRIUMPHANT_EXPERIENCE_YOUTUBE)
            ),
            403,
        );

        return $next($request);
    }
}
