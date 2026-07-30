<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Deployment sits behind Nginx, which terminates TLS and forwards over
        // plain HTTP. Without this, Laravel sees the forwarded request as
        // insecure and route()/asset() emit http:// links on an https:// site —
        // mixed content, and redirect loops when the server forces HTTPS.
        // TRUSTED_PROXIES defaults to '*' because the app is only reachable
        // through that proxy; narrow it to the proxy's IP if it is ever exposed.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Belongs on the `web` group rather than beside 'auth', because
        // Livewire's update endpoint is registered with `web` only. A search
        // keystroke on an admin table therefore passes through it too, which is
        // the whole point: a revoked account must not keep working just because
        // the interaction avoids the original route.
        //
        // Illuminate's AuthenticateSession was the first thing tried here, to
        // end other sessions when a password changes. It was dropped: it pins a
        // session to one password hash, so any request that authenticates as a
        // different user in the SAME session is logged out. Real browsers never
        // do that, but the test suite does it constantly (one test, several
        // actingAs() calls), and it broke six existing tests for a reason that
        // had nothing to do with the behaviour under test. App\Support\UserSessions
        // does the same job explicitly, at the two points where it matters.
        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        // Keycloak's back-channel logout call is server-to-server: no browser,
        // no session, and therefore no CSRF token it could possibly send. The
        // signed logout token is what authenticates that request instead, and
        // KeycloakController refuses anything it cannot verify against the
        // realm's JWKS. This exemption is scoped to that one path.
        $middleware->validateCsrfTokens(except: [
            'auth/keycloak/backchannel-logout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
