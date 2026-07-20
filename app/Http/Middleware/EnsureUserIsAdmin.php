<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to admins (users.role = 'admin'). A logged-in pegawai who
 * reaches an admin route gets a hard 403 (not a redirect). Unauthenticated
 * users are handled earlier by the 'auth' middleware.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Halaman ini hanya untuk administrator.');
        }

        return $next($request);
    }
}
