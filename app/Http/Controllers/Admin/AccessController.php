<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationAccess;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin — Manajemen Hak Akses (application_access per employee).
 *
 * Access rule: can_access(user, app) = role='admin' OR a row exists in
 * application_access. Admins therefore have no rows (they bypass); the UI shows
 * "Semua (bypass)" for them.
 */
class AccessController extends Controller
{
    /** Application categories (mirrors the DB CHECK constraint). */
    private const CATEGORIES = [
        'governance', 'economy', 'kinerja', 'gawai', 'rencana', 'uang',
        'pajak', 'kesehatan', 'data', 'wisata', 'umum',
    ];

    /**
     * The list itself (live search + OPD filter + pagination) is rendered by the
     * <livewire:admin.access-table> component, so it filters as you type while
     * the query stays server-side across the whole dataset.
     */
    public function index()
    {
        return view('admin.akses.index');
    }

    public function edit(User $user)
    {
        $user->load('opd');

        // The whole app list is loaded (no pagination) so the "Atur Akses" page
        // can filter/search/toggle instantly with Alpine on the client.
        $apps = Application::with('opd')
            ->orderBy('name')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'opd' => optional($a->opd)->code,
                'category' => $a->category,
                'active' => (bool) $a->is_active,
            ])
            ->values();

        $grantedIds = $user->applicationAccess()
            ->pluck('application_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return view('admin.akses.edit', [
            'user' => $user,
            'apps' => $apps,
            'grantedIds' => $grantedIds,
            'totalApps' => $apps->count(),
            'opds' => Opd::orderBy('name')->get(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function update(Request $request, User $user)
    {
        // Admins bypass access checks entirely — nothing to sync.
        if ($user->isAdmin()) {
            return redirect()
                ->route('admin.akses.edit', $user)
                ->with('status', 'Admin memiliki akses ke semua aplikasi (bypass); tidak perlu diatur.');
        }

        $validated = $request->validate([
            'access' => ['array'],
            'access.*' => ['integer', 'exists:applications,id'],
        ]);

        $wanted = collect($validated['access'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $current = $user->applicationAccess()->pluck('application_id')->map(fn ($id) => (int) $id);

        $toAdd = $wanted->diff($current);
        $toRemove = $current->diff($wanted);

        DB::transaction(function () use ($user, $toAdd, $toRemove) {
            foreach ($toAdd as $applicationId) {
                ApplicationAccess::create([
                    'application_id' => $applicationId,
                    'user_id' => $user->id,
                ]);
            }

            if ($toRemove->isNotEmpty()) {
                ApplicationAccess::where('user_id', $user->id)
                    ->whereIn('application_id', $toRemove->all())
                    ->delete();
            }
        });

        // Effective immediately on the next request — can_access reads live rows,
        // so no re-login is required.
        return redirect()
            ->route('admin.akses.edit', $user)
            ->with('status', "Hak akses diperbarui: +{$toAdd->count()} ditambah, -{$toRemove->count()} dicabut.");
    }
}
