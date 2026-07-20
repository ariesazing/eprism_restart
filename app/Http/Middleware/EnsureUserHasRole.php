<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user !== null && in_array($user->role->value, $roles, true)) {
            return $next($request);
        }

        abort(Response::HTTP_FORBIDDEN);
    }
}