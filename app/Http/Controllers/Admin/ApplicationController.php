<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Opd;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin — Manajemen Aplikasi (`applications`).
 *
 * Applications are never deleted, only deactivated via is_active. Deleting one
 * cascades into its links, every employee's access grants for it, and the visit
 * records the dashboard module counts from. Setting is_active = false already
 * makes it unlaunchable by anyone (admins included, see LaunchController) while
 * the history survives — which is also how the legacy system marked retired
 * applications (field decision, Dinkominfo).
 *
 * App-layer validation mirrors the DB constraints so users get friendly
 * Indonesian errors before hitting them: slug UNIQUE, app_group/category CHECK.
 * Icons can be uploaded to the public disk or referenced through an HTTP(S) URL
 * or a path under public/. Managed uploads are removed when replaced.
 */
class ApplicationController extends Controller
{
    private const AUDIT_FIELDS = [
        'opd_id', 'name', 'slug', 'description', 'icon', 'app_group', 'category',
        'is_active', 'is_new', 'sort_order',
    ];

    private const APP_GROUPS = ['smartcity', 'spbe', 'tools'];

    private const CATEGORIES = [
        'governance', 'economy', 'kinerja', 'gawai', 'rencana', 'uang',
        'pajak', 'kesehatan', 'data', 'wisata', 'umum',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * The list itself (live search + pagination) is rendered by the
     * <livewire:admin.application-table> component, so it filters as you type
     * while the query stays server-side across the whole dataset.
     */
    public function index()
    {
        return view('admin.aplikasi.index');
    }

    public function create()
    {
        return view('admin.aplikasi.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['icon'] = $this->resolveIconPath($request, null, $data['icon_path'] ?? null);
        $data = $this->withoutIconInputs($data);

        $application = Application::create($data);

        $this->activityLogger->record(
            $request,
            ActivityType::APPLICATION_CREATED,
            "Membuat aplikasi \"{$application->name}\".",
            subject: $application,
            properties: ['after' => $application->only(self::AUDIT_FIELDS)],
        );

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
        $before = $application->only(self::AUDIT_FIELDS);
        $data = $this->validateData($request, $application);
        $data['icon'] = $this->resolveIconPath(
            $request,
            $application->icon,
            $data['icon_path'] ?? null,
        );
        $data = $this->withoutIconInputs($data);

        $application->update($data);
        $changes = $this->activityLogger->changes($before, $application->fresh()->only(self::AUDIT_FIELDS));

        if ($this->activityLogger->hasChanges($changes)) {
            $this->activityLogger->record(
                $request,
                ActivityType::APPLICATION_UPDATED,
                "Memperbarui aplikasi \"{$application->name}\".",
                subject: $application,
                properties: $changes,
            );
        }

        return redirect()
            ->route('admin.aplikasi.edit', $application)
            ->with('status', 'Aplikasi diperbarui.');
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
            'icon_path' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }

                    $value = trim((string) $value);
                    $isHttpUrl = filter_var($value, FILTER_VALIDATE_URL)
                        && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
                    $isPublicPath = preg_match('#^/?[A-Za-z0-9][A-Za-z0-9._/-]*$#', $value) === 1
                        && ! str_contains($value, '..')
                        && ! str_contains($value, '\\');

                    if (! $isHttpUrl && ! $isPublicPath) {
                        $fail('URL/path ikon harus berupa URL HTTP/HTTPS atau path aset publik yang valid.');
                    }
                },
            ],
            'icon_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_icon' => ['nullable', 'boolean'],
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
            'icon_path.max' => 'Path atau URL ikon maksimal 255 karakter.',
            'icon_file.image' => 'Berkas ikon tidak valid.',
            'icon_file.mimes' => 'Ikon harus berformat JPG, JPEG, PNG, atau WEBP.',
            'icon_file.max' => 'Ukuran ikon maksimal 5 MB.',
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

    /** @param array<string, mixed> $data */
    private function withoutIconInputs(array $data): array
    {
        unset($data['icon_path'], $data['icon_file'], $data['remove_icon']);

        return $data;
    }

    private function resolveIconPath(Request $request, ?string $currentPath, ?string $submittedPath): ?string
    {
        if ($request->hasFile('icon_file')) {
            $storedPath = $request->file('icon_file')->store('application-icons', 'public');
            $this->deleteManagedIcon($currentPath);

            return '/storage/'.$storedPath;
        }

        if ($request->boolean('remove_icon')) {
            $this->deleteManagedIcon($currentPath);

            return null;
        }

        $submittedPath = filled($submittedPath) ? trim($submittedPath) : null;

        if ($submittedPath !== $currentPath) {
            $this->deleteManagedIcon($currentPath);
        }

        return $submittedPath;
    }

    private function deleteManagedIcon(?string $path): void
    {
        if (! $path || ! str_starts_with($path, '/storage/application-icons/')) {
            return;
        }

        Storage::disk('public')->delete(substr($path, strlen('/storage/')));
    }
}
