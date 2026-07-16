<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\ApplicationVisit;
use Illuminate\Http\Request;

/**
 * Server-side access enforcement + visit recording (FR-A10 / FR-D04).
 *
 * The dashboard only *marks* inaccessible apps; this route is the real gate:
 * a user without access gets a hard 403 (not a redirect, so the app's presence
 * is not leaked by a bounce to the dashboard).
 */
class LaunchController extends Controller
{
    public function launch(Request $request, Application $application, ?string $link = null)
    {
        $user = $request->user();

        // 1. Access enforcement (FR-A10) — 403, never redirect.
        if (! $user->canAccessApp($application)) {
            $this->log($request, 'access_denied', $user->id, $application->id, 'Akses ditolak ke '.$application->name.'.');

            abort(403, 'Anda tidak memiliki akses ke aplikasi ini.');
        }

        // 2. Availability guard — an inactive application is unavailable to
        //    EVERYONE, admin included (is_active = availability, not permission).
        //    Admin toggles this flag from the admin panel; it is not launched.
        //    No visit is recorded for a rejected launch.
        if (! $application->is_active) {
            $this->log($request, 'access_denied', $user->id, $application->id, 'Peluncuran ditolak: aplikasi '.$application->name.' nonaktif.');

            abort(403, 'Aplikasi ini sedang tidak aktif.');
        }

        // 3. Resolve the launch link (must belong to this application). Without an
        //    explicit link, fall back to the first active link.
        $applicationLink = $link !== null
            ? $application->links()->whereKey($link)->first()
            : $application->links()->where('is_active', true)->orderBy('sort_order')->first();

        if (! $applicationLink) {
            abort(404);
        }

        // 4. Availability guard for the specific link — an explicitly requested
        //    inactive link is rejected (the fallback above already skips inactive
        //    links, so this only triggers for an explicit {link} id). No visit.
        if (! $applicationLink->is_active) {
            $this->log($request, 'access_denied', $user->id, $application->id, 'Peluncuran ditolak: tautan '.$applicationLink->label.' pada '.$application->name.' nonaktif.');

            abort(403, 'Tautan aplikasi ini sedang tidak aktif.');
        }

        // 5. Record the visit idempotently: 1x per (link + user + day). The DB
        //    unique index uq_visit_daily is the ultimate guard; firstOrCreate
        //    avoids a duplicate row for repeat clicks the same day.
        ApplicationVisit::firstOrCreate([
            'application_link_id' => $applicationLink->id,
            'user_id' => $user->id,
            'visit_date' => now()->toDateString(),
        ], [
            'application_id' => $application->id,
            'visited_at' => now(),
        ]);

        // 6. Audit trail (FR-A12 / FR-D04).
        $this->log($request, 'app_launched', $user->id, $application->id,
            'Membuka '.$application->name.' ('.$applicationLink->label.').');

        // 7. Redirect out to the target application (opened in a new tab by the UI).
        return redirect()->away($applicationLink->url);
    }

    private function log(Request $request, string $type, ?int $userId, ?int $applicationId, string $description): void
    {
        ActivityLog::create([
            'user_id' => $userId,
            'application_id' => $applicationId,
            'activity_type' => $type,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
