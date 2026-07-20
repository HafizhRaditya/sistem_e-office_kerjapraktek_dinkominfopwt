<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class QuestionnaireController extends Controller
{
    public function statistics(Request $request)
    {
        $validated = $request->validate([
            'questionnaire' => ['nullable', 'integer', 'exists:questionnaires,id'],
            'status' => ['nullable', 'in:all,responded,pending'],
            'opd' => ['nullable', 'integer', 'exists:opds,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'tab' => ['nullable', 'in:summary,opd,employees'],
        ]);

        $questionnaires = Questionnaire::query()
            ->withCount('responses')
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $selected = $questionnaires->firstWhere('id', (int) ($validated['questionnaire'] ?? 0))
            ?? $questionnaires->first();

        $employees = collect();
        $respondedIds = collect();
        $opdStats = collect();
        $opds = collect();
        $filteredEmployees = collect();
        $metrics = [
            'total_target' => 0,
            'active_responded' => 0,
            'active_pending' => 0,
            'percentage' => 0,
            'total_clicks' => 0,
        ];

        if ($selected) {
            $employees = User::query()
                ->with('opd:id,code,name')
                ->where('role', 'pegawai')
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $respondedIds = QuestionnaireResponse::query()
                ->where('questionnaire_id', $selected->id)
                ->whereIn('user_id', $employees->pluck('id'))
                ->pluck('user_id')
                ->flip();

            $totalTarget = $employees->count();
            $activeResponded = $respondedIds->count();
            $metrics = [
                'total_target' => $totalTarget,
                'active_responded' => $activeResponded,
                'active_pending' => max($totalTarget - $activeResponded, 0),
                'percentage' => $totalTarget > 0 ? round(($activeResponded / $totalTarget) * 100, 1) : 0,
                'total_clicks' => (int) $selected->responses_count,
            ];

            $opdStats = $employees
                ->groupBy('opd_id')
                ->map(function ($group) use ($respondedIds): array {
                    $responded = $group->filter(fn (User $user): bool => $respondedIds->has($user->id))->count();

                    return [
                        'opd' => $group->first()->opd,
                        'total' => $group->count(),
                        'responded' => $responded,
                        'pending' => $group->count() - $responded,
                        'percentage' => $group->count() > 0 ? round(($responded / $group->count()) * 100, 1) : 0,
                    ];
                })
                ->sortBy(fn (array $stat): string => $stat['opd']->name)
                ->values();

            $opds = $employees
                ->pluck('opd')
                ->unique('id')
                ->sortBy('name')
                ->values();

            $filteredEmployees = $employees
                ->when(($validated['status'] ?? 'all') === 'responded', fn ($list) => $list->filter(fn (User $user): bool => $respondedIds->has($user->id)))
                ->when(($validated['status'] ?? 'all') === 'pending', fn ($list) => $list->reject(fn (User $user): bool => $respondedIds->has($user->id)))
                ->when(isset($validated['opd']), fn ($list) => $list->filter(fn (User $user): bool => (int) $user->opd_id === (int) $validated['opd']))
                ->when(filled($validated['q'] ?? null), function ($list) use ($validated) {
                    $needle = mb_strtolower($validated['q']);

                    return $list->filter(fn (User $user): bool => str_contains(mb_strtolower($user->name), $needle)
                        || str_contains(mb_strtolower($user->nip_nik), $needle));
                })
                ->values();

            $page = max($request->integer('page', 1), 1);
            $filteredEmployees = new LengthAwarePaginator(
                $filteredEmployees->forPage($page, 10)->values(),
                $filteredEmployees->count(),
                10,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        }

        return view('admin.kuisioner.statistics', [
            'questionnaires' => $questionnaires,
            'selected' => $selected,
            'metrics' => $metrics,
            'opdStats' => $opdStats,
            'opds' => $opds,
            'employees' => $filteredEmployees,
            'respondedIds' => $respondedIds,
        ]);
    }
}
