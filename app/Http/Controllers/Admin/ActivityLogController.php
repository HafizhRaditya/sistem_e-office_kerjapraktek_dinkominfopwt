<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\ActivityType;
use Illuminate\Http\Request;

/** Admin — read-only audit trail viewer. */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'actor' => ['nullable', 'integer', 'exists:users,id'],
            // Backward compatibility for old links/tests that still use ?user=.
            'user' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['nullable', 'string', 'max:50'],
            'subject_type' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], [
            'actor.integer' => 'Pelaku harus berupa angka bulat.',
            'actor.exists' => 'Pelaku yang dipilih tidak valid.',
            'user.integer' => 'Pengguna harus berupa angka bulat.',
            'user.exists' => 'Pengguna yang dipilih tidak valid.',
            'from.date' => 'Tanggal awal tidak valid.',
            'to.date' => 'Tanggal akhir tidak valid.',
            'to.after_or_equal' => 'Tanggal akhir tidak boleh lebih awal daripada tanggal awal.',
        ]);

        $actorId = $request->filled('actor')
            ? (int) $request->input('actor')
            : ($request->filled('user') ? (int) $request->input('user') : null);

        $logs = ActivityLog::query()
            ->with(['actor:id,name,nip_nik', 'application:id,name', 'questionnaire:id,title'])
            ->when($actorId !== null, fn ($q) => $q->where('user_id', $actorId))
            ->when($request->filled('type'), fn ($q) => $q->where('activity_type', $request->input('type')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->input('subject_type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $typeLabels = ActivityType::labels();

        return view('admin.log.index', [
            'logs' => $logs,
            // Show every supported activity type, even before that type exists in the log table.
            'types' => array_keys($typeLabels),
            'typeLabels' => $typeLabels,
            'subjectTypes' => ActivityLog::query()
                ->whereNotNull('subject_type')
                ->select('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type'),
            'actors' => User::orderBy('name')->get(['id', 'name', 'nip_nik']),
        ]);
    }
}
