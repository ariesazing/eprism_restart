<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAuthenticatedCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        $authenticated = $request->user() !== null;
        $response = $next($request);
        if ($authenticated || $request->user() !== null) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
