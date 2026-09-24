<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\PreventAuthenticatedCaching;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Traefik (Dokploy): trust the reverse proxy so Laravel sees
        // the original HTTPS scheme, client IP, and host.
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [PreventAuthenticatedCaching::class]);

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);

        // Called by ONLYOFFICE Document Server itself (server-to-server, never a logged-in
        // browser session) — it has no CSRF token to send and never will. These routes are
        // authorized purely by ValidateSignature::relative() plus (for the callback) DS's own
        // JWT — see routes/web.php's onlyoffice group.
        $middleware->validateCsrfTokens(except: ['onlyoffice/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['_token', 'token', 'current_password', 'password', 'password_confirmation']);
        // Exceptions bypass the web middleware's response branch (including login validation).
        $exceptions->respond(function (Response $response) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Referrer-Policy', 'no-referrer');

            return $response;
        });
    })->create();
