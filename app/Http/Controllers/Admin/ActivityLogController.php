<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin — Log Aktivitas viewer (FR-A12). Read-only: paginated table with
 * filters by user, activity type, and date range (all server-side).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // Guard the filter inputs so a malformed value can't reach the query.
        $request->validate([
            'user' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ], [
            'from.date' => 'Tanggal awal tidak valid.',
            'to.date' => 'Tanggal akhir tidak valid.',
        ]);

        $logs = ActivityLog::query()
            ->with(['user', 'application'])
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', (int) $request->input('user')))
            ->when($request->filled('type'), fn ($q) => $q->where('activity_type', $request->input('type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.log.index', [
            'logs' => $logs,
            // Real types present in the data — not a hard-coded guess.
            'types' => ActivityLog::query()->select('activity_type')->distinct()->orderBy('activity_type')->pluck('activity_type'),
            'users' => User::orderBy('name')->get(['id', 'name', 'nip_nik']),
        ]);
    }
}
