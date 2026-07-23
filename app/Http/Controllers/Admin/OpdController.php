<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Opd;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin — master data OPD.
 *
 * OPD tidak dihapus permanen karena menjadi induk pengguna dan aplikasi.
 * Penonaktifan mempertahankan seluruh relasi dan histori, serta dapat dibatalkan.
 */
class OpdController extends Controller
{
    private const AUDIT_FIELDS = ['code', 'name', 'is_active'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ], [
            'q.max' => 'Kata pencarian maksimal 100 karakter.',
            'status.in' => 'Filter status OPD tidak valid.',
        ]);

        $term = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;

        $opds = Opd::query()
            ->withCount(['users', 'applications'])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($nested) use ($term) {
                    $nested->where('code', 'ilike', "%{$term}%")
                        ->orWhere('name', 'ilike', "%{$term}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.opd.index', compact('opds'));
    }

    public function create()
    {
        return view('admin.opd.create');
    }

    public function store(Request $request)
    {
        $opd = Opd::create($this->validatedData($request));

        $this->activityLogger->record(
            $request,
            ActivityType::OPD_CREATED,
            "Membuat OPD \"{$opd->name}\" ({$opd->code}).",
            subject: $opd,
            properties: ['after' => $opd->only(self::AUDIT_FIELDS)],
        );

        return redirect()
            ->route('admin.opds.index')
            ->with('status', "OPD \"{$opd->name}\" ditambahkan.");
    }

    public function edit(Opd $opd)
    {
        $opd->loadCount(['users', 'applications']);

        return view('admin.opd.edit', compact('opd'));
    }

    public function update(Request $request, Opd $opd)
    {
        $before = $opd->only(self::AUDIT_FIELDS);
        $opd->update($this->validatedData($request, $opd));
        $opd->refresh();

        $changes = $this->activityLogger->changes($before, $opd->only(self::AUDIT_FIELDS));

        if ($this->activityLogger->hasChanges($changes)) {
            $this->activityLogger->record(
                $request,
                ActivityType::OPD_UPDATED,
                "Memperbarui OPD \"{$opd->name}\" ({$opd->code}).",
                subject: $opd,
                properties: $changes,
            );
        }

        return redirect()
            ->route('admin.opds.edit', $opd)
            ->with('status', 'Data OPD diperbarui.');
    }

    public function status(Request $request, Opd $opd)
    {
        $before = (bool) $opd->is_active;
        $opd->update(['is_active' => ! $before]);
        $opd->refresh();

        $type = $opd->is_active
            ? ActivityType::OPD_ACTIVATED
            : ActivityType::OPD_DEACTIVATED;

        $this->activityLogger->record(
            $request,
            $type,
            ($opd->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." OPD \"{$opd->name}\" ({$opd->code}).",
            subject: $opd,
            properties: [
                'before' => ['is_active' => $before],
                'after' => ['is_active' => (bool) $opd->is_active],
            ],
        );

        return back()->with('status', $opd->is_active
            ? "OPD \"{$opd->name}\" diaktifkan."
            : "OPD \"{$opd->name}\" dinonaktifkan. Pengguna, aplikasi, dan histori tetap tersimpan.");
    }

    private function validatedData(Request $request, ?Opd $opd = null): array
    {
        $request->merge([
            'code' => Str::upper(trim((string) $request->input('code'))),
            'name' => trim((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                Rule::unique('opds', 'code')->ignore($opd?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
        ], [
            'code.required' => 'Kode OPD wajib diisi.',
            'code.max' => 'Kode OPD maksimal 30 karakter.',
            'code.regex' => 'Kode OPD hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.',
            'code.unique' => 'Kode OPD sudah digunakan.',
            'name.required' => 'Nama OPD wajib diisi.',
            'name.max' => 'Nama OPD maksimal 150 karakter.',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
