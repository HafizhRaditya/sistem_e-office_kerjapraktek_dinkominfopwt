<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Admin — Manajemen Banner dashboard.
 *
 * A banner may use an uploaded image, an external URL, or a path beneath
 * public/. Uploaded files are stored on the public disk under banners/.
 */
class BannerController extends Controller
{
    private const AUDIT_FIELDS = [
        'title', 'description', 'image_path', 'target_url', 'is_active',
        'starts_at', 'ends_at', 'sort_order',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status', 'all');
        $now = now();

        $banners = Banner::query()
            ->with('creator:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->when($status === 'active', function ($query) use ($now): void {
                $query
                    ->where('is_active', true)
                    ->where(fn ($period) => $period->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                    ->where(fn ($period) => $period->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
            })
            ->when($status === 'scheduled', fn ($query) => $query->where('is_active', true)->where('starts_at', '>', $now))
            ->when($status === 'expired', fn ($query) => $query->where('is_active', true)->whereNotNull('ends_at')->where('ends_at', '<', $now))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.banner.index', compact('banners', 'search', 'status'));
    }

    public function create()
    {
        return view('admin.banner.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;
        $data['image_path'] = $this->resolveImagePath($request, null, $data['image_path'] ?? null);
        unset($data['image'], $data['remove_image']);

        $banner = Banner::create($data);

        $this->activityLogger->record(
            $request,
            ActivityType::BANNER_CREATED,
            "Membuat banner \"{$banner->title}\".",
            subject: $banner,
            properties: ['after' => $banner->only(self::AUDIT_FIELDS)],
        );

        return redirect()
            ->route('admin.banners.edit', $banner)
            ->with('status', "Banner \"{$banner->title}\" berhasil ditambahkan.");
    }

    public function edit(Banner $banner)
    {
        $banner->load('creator:id,name');

        return view('admin.banner.edit', compact('banner'));
    }

    public function update(Request $request, Banner $banner)
    {
        $before = $banner->only(self::AUDIT_FIELDS);
        $data = $this->validateData($request);
        $data['image_path'] = $this->resolveImagePath(
            $request,
            $banner->image_path,
            $data['image_path'] ?? null,
        );
        unset($data['image'], $data['remove_image']);

        $banner->update($data);
        $changes = $this->activityLogger->changes($before, $banner->fresh()->only(self::AUDIT_FIELDS));

        if ($this->activityLogger->hasChanges($changes)) {
            $this->activityLogger->record(
                $request,
                ActivityType::BANNER_UPDATED,
                "Memperbarui banner \"{$banner->title}\".",
                subject: $banner,
                properties: $changes,
            );
        }

        return redirect()
            ->route('admin.banners.edit', $banner)
            ->with('status', 'Banner berhasil diperbarui.');
    }

    public function destroy(Request $request, Banner $banner)
    {
        $title = $banner->title;
        $imagePath = $banner->image_path;

        DB::transaction(function () use ($request, $banner): void {
            $this->activityLogger->record(
                $request,
                ActivityType::BANNER_DELETED,
                "Menghapus banner \"{$banner->title}\".",
                subject: $banner,
                properties: ['before' => $banner->only(self::AUDIT_FIELDS)],
            );

            $banner->delete();
        });

        $this->deleteManagedImage($imagePath);

        return redirect()
            ->route('admin.banners.index')
            ->with('status', "Banner \"{$title}\" berhasil dihapus.");
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'image_path' => [
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
            'target_url' => ['nullable', 'url:http,https', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
        ], [
            'title.required' => 'Judul banner wajib diisi.',
            'title.max' => 'Judul banner maksimal 200 karakter.',
            'image.image' => 'Berkas gambar tidak valid.',
            'image.mimes' => 'Gambar harus berformat JPG, JPEG, PNG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 5 MB.',
            'image_path.max' => 'Path atau URL gambar maksimal 255 karakter.',
            'target_url.url' => 'Tautan tujuan harus berupa URL HTTP atau HTTPS yang valid.',
            'target_url.max' => 'Tautan tujuan maksimal 500 karakter.',
            'starts_at.date' => 'Waktu mulai tidak valid.',
            'ends_at.date' => 'Waktu selesai tidak valid.',
            'ends_at.after_or_equal' => 'Waktu selesai tidak boleh lebih awal daripada waktu mulai.',
            'sort_order.required' => 'Urutan banner wajib diisi.',
            'sort_order.integer' => 'Urutan banner harus berupa angka bulat.',
            'sort_order.min' => 'Urutan banner tidak boleh negatif.',
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function resolveImagePath(Request $request, ?string $currentPath, ?string $submittedPath): ?string
    {
        if ($request->hasFile('image')) {
            $this->deleteManagedImage($currentPath);

            $storedPath = $request->file('image')->store('banners', 'public');

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
        if (! $path || ! str_starts_with($path, '/storage/banners/')) {
            return;
        }

        Storage::disk('public')->delete(substr($path, strlen('/storage/')));
    }
}
