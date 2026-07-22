@extends('layouts.admin')

@section('title', 'Log Aktivitas')
@section('heading', 'Log Aktivitas')

@section('content')
@php
    $badge = [
        'login_success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'login_failed' => 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'logout' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        'password_changed' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'password_reset' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'app_launched' => 'bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        'access_denied' => 'bg-brand/10 text-brand',
        'quiz_clicked' => 'bg-violet-50 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
        'user_created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'user_updated' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'user_activated' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'user_deactivated' => 'bg-orange-50 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
        'access_updated' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        'application_created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'application_updated' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'application_link_created' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300',
        'application_link_updated' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300',
        'banner_created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'banner_updated' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'banner_deleted' => 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'questionnaire_created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'questionnaire_updated' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'questionnaire_deleted' => 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];

    $subjectLabels = [
        'user' => 'Pengguna',
        'application' => 'Aplikasi',
        'application_link' => 'Tautan aplikasi',
        'banner' => 'Banner',
        'questionnaire' => 'Kuisioner',
        'login_identity' => 'Identitas login',
    ];
@endphp

<div class="w-full max-w-none">
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Jejak aktivitas portal dan tindakan administratif. Kolom <strong>Pelaku</strong> menunjukkan siapa yang bertindak, sedangkan <strong>Objek</strong> menunjukkan data yang terkena tindakan.
    </p>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm font-medium text-brand">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('admin.logs.index') }}" class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6 xl:items-end">
        <div class="xl:col-span-2">
            <label for="actor" class="mb-1 block text-xs font-medium text-slate-500">Pelaku</label>
            <select id="actor" name="actor" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
                <option value="">Semua pelaku</option>
                @foreach ($actors as $actor)
                    <option value="{{ $actor->id }}" @selected((string) request('actor') === (string) $actor->id)>
                        {{ $actor->name }} ({{ $actor->nip_nik }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="type" class="mb-1 block text-xs font-medium text-slate-500">Jenis aktivitas</label>
            <select id="type" name="type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
                <option value="">Semua jenis</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ $typeLabels[$type] ?? $type }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="subject_type" class="mb-1 block text-xs font-medium text-slate-500">Jenis objek</label>
            <select id="subject_type" name="subject_type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
                <option value="">Semua objek</option>
                @foreach ($subjectTypes as $subjectType)
                    <option value="{{ $subjectType }}" @selected(request('subject_type') === $subjectType)>{{ $subjectLabels[$subjectType] ?? $subjectType }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="from" class="mb-1 block text-xs font-medium text-slate-500">Dari tanggal</label>
            <input id="from" name="from" type="date" value="{{ request('from') }}"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
        </div>

        <div>
            <label for="to" class="mb-1 block text-xs font-medium text-slate-500">Sampai tanggal</label>
            <input id="to" name="to" type="date" value="{{ request('to') }}"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
        </div>

        <div class="flex items-center gap-3 sm:col-span-2 xl:col-span-6">
            <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">Terapkan</button>
            @if (request('actor') || request('type') || request('subject_type') || request('from') || request('to'))
                <a href="{{ route('admin.logs.index') }}" class="text-sm font-medium text-slate-500 hover:text-brand">Reset</a>
            @endif
        </div>
    </form>

    <p class="mt-4 text-xs text-slate-400">{{ number_format($logs->total(), 0, ',', '.') }} entri</p>

    <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full min-w-[1100px] text-sm">
            <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-400 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3 font-semibold">Waktu</th>
                    <th class="px-5 py-3 font-semibold">Pelaku</th>
                    <th class="px-5 py-3 font-semibold">Aktivitas</th>
                    <th class="px-5 py-3 font-semibold">Objek</th>
                    <th class="px-5 py-3 font-semibold">Keterangan</th>
                    <th class="px-5 py-3 font-semibold">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($logs as $log)
                    <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500">{{ optional($log->created_at)->format('d/m/Y H:i:s') }}</td>
                        <td class="px-5 py-3">
                            @if ($log->actor)
                                <p class="font-medium">{{ $log->actor->name }}</p>
                                <p class="font-mono text-xs text-slate-400">{{ $log->actor->nip_nik }}</p>
                            @else
                                <span class="text-slate-400">Tidak diketahui</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $badge[$log->activity_type] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                                {{ $typeLabels[$log->activity_type] ?? $log->activity_type }}
                            </span>
                            <p class="mt-1 font-mono text-[10px] text-slate-400">{{ $log->activity_type }}</p>
                        </td>
                        <td class="px-5 py-3">
                            @if ($log->subject_label)
                                <p class="font-medium text-slate-700 dark:text-slate-200">{{ $log->subject_label }}</p>
                                <p class="text-xs text-slate-400">
                                    {{ $subjectLabels[$log->subject_type] ?? $log->subject_type }}
                                    @if ($log->subject_id) #{{ $log->subject_id }} @endif
                                </p>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="max-w-md px-5 py-3 text-slate-600 dark:text-slate-300">
                            <p>{{ $log->description ?? '—' }}</p>
                            @if ($log->properties)
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs font-medium text-brand">Detail perubahan</summary>
                                    <pre class="mt-2 max-w-md overflow-x-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-[11px] leading-relaxed text-slate-600 dark:bg-slate-950 dark:text-slate-300">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </td>
                        <td class="px-5 py-3 font-mono text-xs text-slate-400">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="6"
                        :filtered="request()->filled('actor') || request()->filled('type') || request()->filled('subject_type') || request()->filled('from') || request()->filled('to')"
                        title="Belum ada aktivitas tercatat"
                        hint="Log terisi otomatis saat pengguna beraktivitas atau admin mengubah data."
                        filtered-hint="Coba ubah pelaku, jenis aktivitas, objek, atau rentang tanggal." />
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
