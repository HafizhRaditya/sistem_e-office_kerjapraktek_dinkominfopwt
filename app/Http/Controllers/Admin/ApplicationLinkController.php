<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationLink;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin — Manajemen Tautan Aplikasi (CRUD `application_links`), nested under an
 * application. Enforces UNIQUE(application_id, label) with an app-layer message.
 */
class ApplicationLinkController extends Controller
{
    public function create(Application $application)
    {
        return view('admin.aplikasi.link.create', ['application' => $application]);
    }

    public function store(Request $request, Application $application)
    {
        $application->links()->create($this->validateData($request, $application));

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
        $link->update($this->validateData($request, $application, $link));

        return redirect()
            ->route('admin.aplikasi.edit', $application)
            ->with('status', 'Tautan diperbarui.');
    }

    public function destroy(Application $application, ApplicationLink $link)
    {
        $this->ensureOwned($application, $link);
        $link->delete();

        return redirect()
            ->route('admin.aplikasi.edit', $application)
            ->with('status', 'Tautan dihapus.');
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
