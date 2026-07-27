<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    private const AUDIT_FIELDS = ['name', 'slug', 'is_active', 'sort_order'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ], [
            'q.max' => 'Kata pencarian maksimal 100 karakter.',
            'status.in' => 'Filter status kategori tidak valid.',
        ]);

        $term = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;

        $categories = Category::query()
            ->withCount('applications')
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested
                        ->where('name', 'ilike', "%{$term}%")
                        ->orWhere('slug', 'ilike', "%{$term}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.kategori.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.kategori.create');
    }

    public function store(Request $request)
    {
        $category = Category::create($this->validatedData($request));

        $this->activityLogger->record(
            $request,
            ActivityType::CATEGORY_CREATED,
            "Membuat kategori \"{$category->name}\".",
            subject: $category,
            properties: ['after' => $category->only(self::AUDIT_FIELDS)],
        );

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Kategori \"{$category->name}\" ditambahkan.");
    }

    public function edit(Category $category)
    {
        $category->loadCount('applications');

        return view('admin.kategori.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $before = $category->only(self::AUDIT_FIELDS);
        $category->update($this->validatedData($request, $category));
        $category->refresh();

        $changes = $this->activityLogger->changes($before, $category->only(self::AUDIT_FIELDS));

        if ($this->activityLogger->hasChanges($changes)) {
            $this->activityLogger->record(
                $request,
                ActivityType::CATEGORY_UPDATED,
                "Memperbarui kategori \"{$category->name}\".",
                subject: $category,
                properties: $changes,
            );
        }

        return redirect()
            ->route('admin.categories.edit', $category)
            ->with('status', 'Kategori diperbarui.');
    }

    public function status(Request $request, Category $category)
    {
        $before = (bool) $category->is_active;
        $category->update(['is_active' => ! $before]);
        $category->refresh();

        $this->activityLogger->record(
            $request,
            $category->is_active
                ? ActivityType::CATEGORY_ACTIVATED
                : ActivityType::CATEGORY_DEACTIVATED,
            ($category->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." kategori \"{$category->name}\".",
            subject: $category,
            properties: [
                'before' => ['is_active' => $before],
                'after' => ['is_active' => (bool) $category->is_active],
            ],
        );

        return back()->with('status', $category->is_active
            ? "Kategori \"{$category->name}\" diaktifkan."
            : "Kategori \"{$category->name}\" dinonaktifkan. Aplikasi yang terhubung tetap tampil di dashboard.");
    }

    private function validatedData(Request $request, ?Category $category = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.max' => 'Nama kategori maksimal 100 karakter.',
            'slug.required' => 'Slug kategori wajib diisi.',
            'slug.regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
            'slug.unique' => 'Slug sudah digunakan kategori lain.',
            'sort_order.required' => 'Urutan kategori wajib diisi.',
            'sort_order.integer' => 'Urutan kategori harus berupa angka bulat.',
            'sort_order.min' => 'Urutan kategori tidak boleh negatif.',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
