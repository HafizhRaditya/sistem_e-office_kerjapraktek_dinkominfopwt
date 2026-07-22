<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Application;
use App\Models\ApplicationAccess;
use App\Models\ApplicationLink;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin — Ringkasan (overview of the auth & access-control module).
 *
 * Read-only landing page for the admin panel. Scope is deliberately limited to
 * this module's own data — users, OPD spread, applications/links, access grants,
 * and a short activity tail. Questionnaire and banner metrics belong to the
 * dashboard module and have their own page (Statistik Kuisioner).
 *
 * The recent-activity strip is a pointer, not a replacement for Log Aktivitas:
 * five rows, no filters, linking through to the full viewer.
 */
class OverviewController extends Controller
{
    /** How many recent logins to show. Deliberately short. */
    private const RECENT_LIMIT = 5;

    public function index()
    {
        return view('admin.ringkasan.index', [
            'users' => $this->userMetrics(),
            'usersByOpd' => $this->usersByOpd(),
            'applications' => $this->applicationMetrics(),
            'access' => $this->accessMetrics(),
            'recentLogins' => $this->recentLogins(),
        ]);
    }

    /**
     * Head counts by role and status, in one pass over the table rather than a
     * query per figure.
     *
     * @return array<string, int>
     */
    private function userMetrics(): array
    {
        $row = User::query()
            ->selectRaw('count(*) AS total')
            ->selectRaw("count(*) FILTER (WHERE role = 'admin') AS admins")
            ->selectRaw("count(*) FILTER (WHERE role = 'pegawai') AS pegawai")
            ->selectRaw('count(*) FILTER (WHERE is_active) AS active')
            ->selectRaw('count(*) FILTER (WHERE NOT is_active) AS inactive')
            ->first();

        return [
            'total' => (int) $row->total,
            'admins' => (int) $row->admins,
            'pegawai' => (int) $row->pegawai,
            'active' => (int) $row->active,
            'inactive' => (int) $row->inactive,
        ];
    }

    /**
     * User spread per OPD. Every OPD is listed, including those with no user
     * yet — a zero is information too.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function usersByOpd()
    {
        return Opd::query()
            ->leftJoin('users', 'users.opd_id', '=', 'opds.id')
            ->groupBy('opds.id', 'opds.code', 'opds.name')
            ->orderByDesc(DB::raw('count(users.id)'))
            ->orderBy('opds.code')
            ->get([
                'opds.code',
                'opds.name',
                DB::raw('count(users.id) AS total'),
                DB::raw('count(users.id) FILTER (WHERE users.is_active) AS active'),
            ]);
    }

    /** @return array<string, int> */
    private function applicationMetrics(): array
    {
        $apps = Application::query()
            ->selectRaw('count(*) AS total')
            ->selectRaw('count(*) FILTER (WHERE is_active) AS active')
            ->selectRaw('count(*) FILTER (WHERE NOT is_active) AS inactive')
            ->first();

        $links = ApplicationLink::query()
            ->selectRaw('count(*) AS total')
            ->selectRaw('count(*) FILTER (WHERE is_active) AS active')
            ->first();

        return [
            'total' => (int) $apps->total,
            'active' => (int) $apps->active,
            'inactive' => (int) $apps->inactive,
            'links' => (int) $links->total,
            'links_active' => (int) $links->active,
        ];
    }

    /**
     * Access grants. `pegawai_with_access` counts employees who hold at least
     * one grant — admins are excluded because they bypass application_access
     * entirely and would otherwise read as "no access".
     *
     * @return array<string, int>
     */
    private function accessMetrics(): array
    {
        $pegawaiWithAccess = User::query()
            ->where('role', 'pegawai')
            ->whereHas('applicationAccess')
            ->count();

        return [
            'grants' => ApplicationAccess::count(),
            'pegawai_with_access' => $pegawaiWithAccess,
            'pegawai_without_access' => User::where('role', 'pegawai')->count() - $pegawaiWithAccess,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, ActivityLog> */
    private function recentLogins()
    {
        return ActivityLog::query()
            ->with('user:id,name,nip_nik')
            ->where('activity_type', 'login_success')
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get();
    }
}
