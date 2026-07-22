<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Models\QuestionnaireResponse;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Admin — CRUD kuisioner dan statistik partisipasi.
 *
 * Gambar unggahan disimpan pada public disk di questionnaires/. Kuisioner yang
 * sudah mempunyai respons tidak boleh dihapus agar riwayat statistik terjaga.
 */
class QuestionnaireController extends Controller
{
    private const AUDIT_FIELDS = [
        'title', 'description', 'banner_image', 'target_url', 'is_active',
        'starts_at', 'ends_at', 'sort_order',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status', 'all');
        $status = in_array($status, ['all', 'active', 'scheduled', 'expired', 'inactive'], true)
            ? $status
            : 'all';
        $now = now();

        $questionnaires = Questionnaire::query()
            ->with('creator:id,name')
            ->withCount('responses')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhere('target_url', 'ilike', "%{$search}%");
                });
            })
            ->when($status === 'active', function ($query) use ($now): void {
                $query
                    ->where('is_active', true)
                    ->where(fn ($period) => $period->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn ($period) => $period->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
            })
            ->when($status === 'scheduled', function ($query) use ($now): void {
                $query->where('is_active', true)->where('starts_at', '>', $now);
            })
            ->when($status === 'expired', function ($query) use ($now): void {
                $query->where('is_active', true)->whereNotNull('ends_at')->where('ends_at', '<', $now);
            })
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.kuisioner.index', compact('questionnaires', 'search', 'status'));
    }

    public function create()
    {
        return view('admin.kuisioner.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;
        $data['banner_image'] = $this->resolveImagePath(
            $request,
            null,
            $data['banner_image'] ?? null,
        );
        unset($data['image'], $data['remove_image']);

        $questionnaire = Questionnaire::create($data);

        $this->activityLogger->record(
            $request,
            ActivityType::QUESTIONNAIRE_CREATED,
            "Membuat kuisioner \"{$questionnaire->title}\".",
            subject: $questionnaire,
            properties: ['after' => $questionnaire->only(self::AUDIT_FIELDS)],
        );

        return redirect()
            ->route('admin.questionnaires.edit', $questionnaire)
            ->with('status', "Kuisioner \"{$questionnaire->title}\" berhasil ditambahkan.");
    }

    public function edit(Questionnaire $questionnaire)
    {
        $questionnaire->load('creator:id,name')->loadCount('responses');

        return view('admin.kuisioner.edit', compact('questionnaire'));
    }

    public function update(Request $request, Questionnaire $questionnaire)
    {
        $before = $questionnaire->only(self::AUDIT_FIELDS);
        $data = $this->validateData($request);
        $data['banner_image'] = $this->resolveImagePath(
            $request,
            $questionnaire->banner_image,
            $data['banner_image'] ?? null,
        );
        unset($data['image'], $data['remove_image']);

        $questionnaire->update($data);
        $changes = $this->activityLogger->changes($before, $questionnaire->fresh()->only(self::AUDIT_FIELDS));

        if ($this->activityLogger->hasChanges($changes)) {
            $this->activityLogger->record(
                $request,
                ActivityType::QUESTIONNAIRE_UPDATED,
                "Memperbarui kuisioner \"{$questionnaire->title}\".",
                subject: $questionnaire,
                properties: $changes,
            );
        }

        return redirect()
            ->route('admin.questionnaires.edit', $questionnaire)
            ->with('status', 'Kuisioner berhasil diperbarui.');
    }

    public function destroy(Request $request, Questionnaire $questionnaire)
    {
        $responsesCount = $questionnaire->responses()->count();

        if ($responsesCount > 0) {
            return redirect()
                ->route('admin.questionnaires.index')
                ->withErrors([
                    'questionnaire' => 'Kuisioner "'.$questionnaire->title.'" tidak dapat dihapus karena sudah memiliki '.$responsesCount.' respons. Nonaktifkan kuisioner untuk mempertahankan riwayat statistik.',
                ]);
        }

        $title = $questionnaire->title;
        $imagePath = $questionnaire->banner_image;

        DB::transaction(function () use ($request, $questionnaire): void {
            $this->activityLogger->record(
                $request,
                ActivityType::QUESTIONNAIRE_DELETED,
                'Menghapus kuisioner "'.$questionnaire->title.'".',
                subject: $questionnaire,
                properties: ['before' => $questionnaire->only(self::AUDIT_FIELDS)],
            );

            $questionnaire->delete();
        });

        $this->deleteManagedImage($imagePath);

        return redirect()
            ->route('admin.questionnaires.index')
            ->with('status', 'Kuisioner "'.$title.'" berhasil dihapus.');
    }

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

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'banner_image' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }

                    $isHttpUrl = filter_var($value, FILTER_VALIDATE_URL)
                        && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
                    $isPublicPath = preg_match('#^/?[A-Za-z0-9][A-Za-z0-9._/-]*$#', $value) === 1;

                    if (! $isHttpUrl && ! $isPublicPath) {
                        $fail('URL/path gambar harus berupa URL HTTP/HTTPS atau path aset publik yang valid.');
                    }
                },
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'target_url' => ['required', 'url:http,https', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ], [
            'title.required' => 'Judul kuisioner wajib diisi.',
            'title.max' => 'Judul kuisioner maksimal 200 karakter.',
            'image.image' => 'Berkas gambar tidak valid.',
            'image.mimes' => 'Gambar harus berformat JPG, JPEG, PNG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 5 MB.',
            'banner_image.max' => 'Path atau URL gambar maksimal 255 karakter.',
            'target_url.required' => 'Tautan formulir wajib diisi.',
            'target_url.url' => 'Tautan formulir harus berupa URL HTTP atau HTTPS yang valid.',
            'target_url.max' => 'Tautan formulir maksimal 500 karakter.',
            'starts_at.date' => 'Waktu mulai tidak valid.',
            'ends_at.date' => 'Waktu selesai tidak valid.',
            'ends_at.after_or_equal' => 'Waktu selesai tidak boleh lebih awal daripada waktu mulai.',
            'sort_order.required' => 'Urutan kuisioner wajib diisi.',
            'sort_order.integer' => 'Urutan kuisioner harus berupa angka bulat.',
            'sort_order.min' => 'Urutan kuisioner tidak boleh negatif.',
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function resolveImagePath(Request $request, ?string $currentPath, ?string $submittedPath): ?string
    {
        if ($request->hasFile('image')) {
            $this->deleteManagedImage($currentPath);

            $storedPath = $request->file('image')->store('questionnaires', 'public');

            return '/storage/'.$storedPath;
        }

        if ($request->boolean('remove_image')) {
            $this->deleteManagedImage($currentPath);

            return null;
        }

        $submittedPath = filled($submittedPath) ? trim($submittedPath) : null;

        if ($submittedPath !== $currentPath) {
            $this->deleteManagedImage($currentPath);
        }

        return $submittedPath;
    }

    private function deleteManagedImage(?string $path): void
    {
        if (! $path || ! str_starts_with($path, '/storage/questionnaires/')) {
            return;
        }

        Storage::disk('public')->delete(substr($path, strlen('/storage/')));
    }
}
