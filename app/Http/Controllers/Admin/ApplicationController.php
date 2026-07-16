<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Opd;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin — Manajemen Aplikasi (CRUD `applications`).
 *
 * App-layer validation mirrors the DB constraints so users get friendly
 * Indonesian errors before hitting them: slug UNIQUE, app_group/category CHECK.
 * The `icon` column is intentionally left untouched (managed via seeded assets).
 */
class ApplicationController extends Controller
{
    private const APP_GROUPS = ['smartcity', 'spbe', 'tools'];

    private const CATEGORIES = [
        'governance', 'economy', 'kinerja', 'gawai', 'rencana', 'uang',
        'pajak', 'kesehatan', 'data', 'wisata', 'umum',
    ];

    public function index(Request $request)
    {
        $applications = Application::query()
            ->with('opd')
            ->withCount('links')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim((string) $request->input('q'));
                $query->where(function ($w) use ($term) {
                    $w->where('name', 'ilike', "%{$term}%")->orWhere('slug', 'ilike', "%{$term}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.aplikasi.index', ['applications' => $applications]);
    }

    public function create()
    {
        return view('admin.aplikasi.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $application = Application::create($data);

        return redirect()
            ->route('admin.aplikasi.edit', $application)
            ->with('status', "Aplikasi \"{$application->name}\" ditambahkan. Sekarang tambahkan tautannya.");
    }

    public function edit(Application $application)
    {
        $application->load(['opd', 'links' => fn ($q) => $q->orderBy('sort_order')->orderBy('label')]);

        return view('admin.aplikasi.edit', array_merge($this->formData(), ['application' => $application]));
    }

    public function update(Request $request, Application $application)
    {
        $application->update($this->validateData($request, $application));

        return redirect()
            ->route('admin.aplikasi.edit', $application)
            ->with('status', 'Aplikasi diperbarui.');
    }

    public function destroy(Application $application)
    {
        $name = $application->name;
        // FK ON DELETE CASCADE also removes its links, access grants, and visits.
        $application->delete();

        return redirect()
            ->route('admin.aplikasi.index')
            ->with('status', "Aplikasi \"{$name}\" dihapus beserta tautan, hak akses, dan kunjungannya.");
    }

    private function formData(): array
    {
        return [
            'opds' => Opd::orderBy('name')->get(),
            'appGroups' => self::APP_GROUPS,
            'categories' => self::CATEGORIES,
        ];
    }

    private function validateData(Request $request, ?Application $application = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'slug' => [
                'required', 'string', 'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('applications', 'slug')->ignore($application?->id),
            ],
            'description' => ['nullable', 'string'],
            'app_group' => ['required', Rule::in(self::APP_GROUPS)],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'sort_order' => ['required', 'integer', 'min:0'],
        ], [
            'name.required' => 'Nama aplikasi wajib diisi.',
            'opd_id.required' => 'OPD pemilik wajib dipilih.',
            'opd_id.exists' => 'OPD yang dipilih tidak valid.',
            'slug.required' => 'Slug wajib diisi.',
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (contoh: e-planning).',
            'slug.unique' => 'Slug sudah dipakai aplikasi lain.',
            'app_group.required' => 'Grup aplikasi wajib dipilih.',
            'app_group.in' => 'Grup aplikasi tidak valid.',
            'category.in' => 'Kategori tidak valid.',
            'sort_order.required' => 'Urutan wajib diisi.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_new'] = $request->boolean('is_new');

        return $validated;
    }
}
