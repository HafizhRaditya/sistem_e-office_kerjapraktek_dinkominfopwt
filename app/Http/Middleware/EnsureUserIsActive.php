<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account stops working on the NEXT request, not at session expiry.
 *
 * Both login paths already refuse an inactive account (AuthController and
 * KeycloakController), but that check only runs at the moment of signing in.
 * Nothing re-read `is_active` afterwards, so "Nonaktifkan" left an already
 * signed-in employee working normally until their session lapsed — up to
 * SESSION_LIFETIME minutes later. For a control an admin reaches for precisely
 * when someone should stop having access, "in about two hours" is the wrong
 * answer.
 *
 * Registered on the `web` group rather than beside 'auth', so it also covers the
 * Livewire update endpoint (Livewire registers that route with the `web` group).
 * Adding it to Livewire's persistent-middleware list instead would leave it
 * running twice on ordinary routes.
 *
 * The role is deliberately NOT re-checked here: EnsureUserIsAdmin already reads
 * it live from the database on every admin request.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_active) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Livewire and any other JSON caller get a hard status: a 302 to the
        // login page would be swallowed by the client and look like an empty
        // response rather than a revoked session.
        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            abort(403, 'Akun Anda dinonaktifkan. Hubungi admin OPD.');
        }

        return redirect()->route('login')->withErrors([
            'nip_nik' => 'Akun Anda dinonaktifkan. Hubungi admin OPD.',
        ]);
    }
}
