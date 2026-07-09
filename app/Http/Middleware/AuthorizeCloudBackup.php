<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeCloudBackup
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user && (
                $user->hasRole('super_admin')
                || $user->can(AdminPermissions::CLOUD_BACKUPS)
            ),
            403,
        );

        return $next($request);
    }
}
