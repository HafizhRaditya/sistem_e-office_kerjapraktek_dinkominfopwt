<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('opd');

        // RBAC rule: ALL applications are always rendered; access is only a flag.
        $applications = Application::query()
            ->with([
                'opd',
                'links' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->orderBy('sort_order')
            ->get();

        // Access set computed once (no query per card). Admin sees all as accessible.
        $accessible = $user->isAdmin()
            ? $applications->pluck('id')->all()
            : $user->accessibleApplicationIds();
        $accessible = array_flip($accessible);

        // Visit aggregates for the current month / year (AB2).
        $monthCounts = DB::table('application_visits')
            ->where('visit_date', '>=', now()->startOfMonth()->toDateString())
            ->selectRaw('application_id, count(*) AS c')
            ->groupBy('application_id')
            ->pluck('c', 'application_id');

        $yearCounts = DB::table('application_visits')
            ->where('visit_date', '>=', now()->startOfYear()->toDateString())
            ->selectRaw('application_id, count(*) AS c')
            ->groupBy('application_id')
            ->pluck('c', 'application_id');

        $userStats = [
            'accessible_apps' => count($accessible),
            'restricted_apps' => max($applications->count() - count($accessible), 0),
            'month_visits' => DB::table('application_visits')
                ->where('user_id', $user->id)
                ->where('visit_date', '>=', now()->startOfMonth()->toDateString())
                ->count(),
            'year_visits' => DB::table('application_visits')
                ->where('user_id', $user->id)
                ->where('visit_date', '>=', now()->startOfYear()->toDateString())
                ->count(),
        ];

        $apps = $applications->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'slug' => $a->slug,
            'icon' => $a->icon ? asset($a->icon) : null,
            'opd' => optional($a->opd)->code,
            'group' => $a->app_group,
            'category' => $a->category,
            'active' => (bool) $a->is_active,
            'is_new' => (bool) $a->is_new,
            'description' => $a->description,
            'can_access' => isset($accessible[$a->id]),
            'month_visits' => (int) ($monthCounts[$a->id] ?? 0),
            'year_visits' => (int) ($yearCounts[$a->id] ?? 0),
            'links' => $a->links->map(fn ($l) => [
                'id' => $l->id,
                'label' => $l->label,
                'is_active' => (bool) $l->is_active,
            ])->values(),
        ])->values();

        return view('dashboard', [
            'apps' => $apps,
            'user' => $user,
            'userStats' => $userStats,
        ]);
    }
}
