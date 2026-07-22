<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationVisit;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
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
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function launch(Request $request, Application $application, ?string $link = null)
    {
        $user = $request->user();

        // 1. Access enforcement (FR-A10) — 403, never redirect.
        if (! $user->canAccessApp($application)) {
            $this->activityLogger->record(
                $request,
                ActivityType::ACCESS_DENIED,
                'Akses ditolak ke '.$application->name.'.',
                subject: $application,
            );

            abort(403, 'Anda tidak memiliki akses ke aplikasi ini.');
        }

        // 2. Availability guard — an inactive application is unavailable to
        //    EVERYONE, admin included (is_active = availability, not permission).
        if (! $application->is_active) {
            $this->activityLogger->record(
                $request,
                ActivityType::ACCESS_DENIED,
                'Peluncuran ditolak: aplikasi '.$application->name.' nonaktif.',
                subject: $application,
            );

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

        // 4. Availability guard for the specific link.
        if (! $applicationLink->is_active) {
            $this->activityLogger->record(
                $request,
                ActivityType::ACCESS_DENIED,
                'Peluncuran ditolak: tautan '.$applicationLink->label.' pada '.$application->name.' nonaktif.',
                subject: $applicationLink,
                applicationId: $application->id,
            );

            abort(403, 'Tautan aplikasi ini sedang tidak aktif.');
        }

        // 5. Record the visit idempotently: 1x per (link + user + day).
        ApplicationVisit::firstOrCreate([
            'application_link_id' => $applicationLink->id,
            'user_id' => $user->id,
            'visit_date' => now()->toDateString(),
        ], [
            'application_id' => $application->id,
            'visited_at' => now(),
        ]);

        // 6. Audit trail (FR-A12 / FR-D04).
        $this->activityLogger->record(
            $request,
            ActivityType::APP_LAUNCHED,
            'Membuka '.$application->name.' ('.$applicationLink->label.').',
            subject: $applicationLink,
            properties: ['application_name' => $application->name],
            applicationId: $application->id,
        );

        // 7. Redirect out to the target application.
        return redirect()->away($applicationLink->url);
    }
}
