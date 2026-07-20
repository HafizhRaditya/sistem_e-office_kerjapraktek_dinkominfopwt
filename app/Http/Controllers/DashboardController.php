<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Banner;
use App\Models\Questionnaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('opd');

        $now = now();
        $withinPeriod = static function ($query) use ($now): void {
            $query
                ->where(function ($period) use ($now): void {
                    $period->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($period) use ($now): void {
                    $period->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                });
        };

        $bannerSlides = Banner::query()
            ->where('is_active', true)
            ->where($withinPeriod)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Banner $banner): array => [
                'id' => $banner->id,
                'type' => 'banner',
                'title' => $banner->title,
                'description' => $banner->description,
                'image' => $this->assetUrl($banner->image_path),
                'target_url' => $banner->target_url,
                'click_url' => null,
                'sort_order' => $banner->sort_order,
            ]);

        $questionnaireSlides = collect();
        $popupSlides = collect();
        if ($user->is_active && $user->role === 'pegawai') {
            $questionnaireSlides = Questionnaire::query()
                ->where('is_active', true)
                ->where($withinPeriod)
                ->whereDoesntHave('responses', fn ($query) => $query->where('user_id', $user->id))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Questionnaire $questionnaire): array => [
                    'id' => $questionnaire->id,
                    'type' => 'questionnaire',
                    'title' => $questionnaire->title,
                    'description' => $questionnaire->description,
                    'image' => $this->assetUrl($questionnaire->banner_image),
                    'target_url' => null,
                    'click_url' => route('questionnaire.click', $questionnaire),
                    'sort_order' => $questionnaire->sort_order,
                ]);

            $popupSlides = $bannerSlides
                ->concat($questionnaireSlides)
                ->sortBy(fn (array $slide): string => sprintf(
                    '%010d-%d-%010d',
                    $slide['sort_order'],
                    $slide['type'] === 'banner' ? 0 : 1,
                    $slide['id'],
                ))
                ->values();
        }

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

        $dayCounts = DB::table('application_visits')
            ->where('visit_date', today()->toDateString())
            ->selectRaw('application_id, count(*) AS c')
            ->groupBy('application_id')
            ->pluck('c', 'application_id');

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
            'day_visits' => (int) ($dayCounts[$a->id] ?? 0),
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
            'heroSlides' => $bannerSlides->values(),
            'popupSlides' => $popupSlides,
        ]);
    }

    private function assetUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return preg_match('/^https?:\/\//i', $path) === 1
            ? $path
            : asset(ltrim($path, '/'));
    }
}
