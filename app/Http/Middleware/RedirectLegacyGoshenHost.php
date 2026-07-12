<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectLegacyGoshenHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower((string) $request->headers->get('host', $request->getHost()));
        $host = preg_replace('/:\d+$/', '', $host) ?: $host;

        if (in_array($host, ['goshen.shotfaz.com', 'www.goshen.shotfaz.com'], true)) {
            return redirect()->away('https://portal.goshenretreat.uk'.$request->getRequestUri(), 302);
        }

        return $next($request);
    }
}
