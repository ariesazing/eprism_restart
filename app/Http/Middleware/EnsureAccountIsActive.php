<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unlike the old "pending approval" gate this replaces, a disabled account isn't a
 * request still waiting on something — it's an admin action (an account "no longer
 * used or any other cause"), so being disabled ends the session outright rather than
 * just hiding a few routes behind it.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isActive()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(Response::HTTP_FORBIDDEN, 'Your account has been disabled.');
        }

        return redirect()->route('login')
            ->withErrors(['email' => 'Your account has been disabled. Please contact an administrator.']);
    }
}
