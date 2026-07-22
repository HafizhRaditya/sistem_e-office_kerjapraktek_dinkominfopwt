<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationLink;
use App\Services\ActivityLogger;
use App\Support\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin — Manajemen Tautan Aplikasi (`application_links`).
 * Links are retired through is_active, never permanently deleted.
 */
class ApplicationLinkController extends Controller
{
    private const AUDIT_FIELDS = ['application_id', 'label', 'url', 'is_active', 'sort_order'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function create(Application $application)
    {
        return view('admin.aplikasi.link.create', ['application' => $application]);
    }

    public function store(Request $request, Application $application)
    {
        $link = $application->links()->create($this->validateData($request, $application));

        $this->activityLogger->record(
            $request,
            ActivityType::APPLICATION_LINK_CREATED,
            "Menambahkan tautan \"{$link->label}\" pada aplikasi \"{$application->name}\".",
            subject: $link,
            properties: [
                'application_name' => $application->name,
                'after' => $link->only(self::AUDIT_FIELDS),
            ],
            applicationId: $application->id,
        );

        return redirect()
            ->route('admin.aplikasi.edit', $application)
            ->with('status', 'Tautan ditambahkan.');
    }

    public function edit(Application $application, ApplicationLink $link)
    {
        $this->ensureOwned($application, $link);

        return view('admin.aplikasi.link.edit', ['application' => $application, 'link' => $link]);
    }

    public function update(Request $request, Application $application, ApplicationLink $link)
    {
        $this->ensureOwned($application, $link);

        $before = $link->only(self::AUDIT_FIELDS);
        $link->update($this->validateData($request, $application, $link));
        $changes = $this->activityLogger->changes($before, $link->fresh()->only(self::AUDIT_FIELDS));

        if ($this->activityLogger->hasChanges($changes)) {
            $this->activityLogger->record(
                $request,
                ActivityType::APPLICATION_LINK_UPDATED,
                "Memperbarui tautan \"{$link->label}\" pada aplikasi \"{$application->name}\".",
                subject: $link,
                properties: array_merge(['application_name' => $application->name], $changes),
                applicationId: $application->id,
            );
        }

        return redirect()
            ->route('admin.aplikasi.edit', $application)
            ->with('status', 'Tautan diperbarui.');
    }

    private function ensureOwned(Application $application, ApplicationLink $link): void
    {
        abort_unless($link->application_id === $application->id, 404);
    }

    private function validateData(Request $request, Application $application, ?ApplicationLink $link = null): array
    {
        $validated = $request->validate([
            'label' => [
                'required', 'string', 'max:50',
                Rule::unique('application_links', 'label')
                    ->where('application_id', $application->id)
                    ->ignore($link?->id),
            ],
            'url' => ['required', 'url', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ], [
            'label.required' => 'Label wajib diisi.',
            'label.unique' => 'Label sudah dipakai pada aplikasi ini.',
            'url.required' => 'URL wajib diisi.',
            'url.url' => 'URL tidak valid (harus diawali http:// atau https://).',
            'sort_order.required' => 'Urutan wajib diisi.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
