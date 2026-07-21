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
        'app_launched' => 'bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        'access_denied' => 'bg-brand/10 text-brand',
        'quiz_clicked' => 'bg-violet-50 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    ];
@endphp
<div class="max-w-6xl">
    <p class="text-sm text-slate-500 dark:text-slate-400">Jejak aktivitas pengguna portal: login, logout, ubah sandi, peluncuran aplikasi, dan penolakan akses.</p>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-brand font-medium">{{ $errors->first() }}</div>
    @endif

    {{-- Filters (server-side; the table is paginated) --}}
    <form method="GET" action="{{ route('admin.logs.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
        <div>
            <label for="user" class="block text-xs font-medium text-slate-500 mb-1">Pengguna</label>
            <select id="user" name="user" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
                <option value="">Semua pengguna</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->name }} ({{ $u->nip_nik }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="type" class="block text-xs font-medium text-slate-500 mb-1">Jenis aktivitas</label>
            <select id="type" name="type" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
                <option value="">Semua jenis</option>
                @foreach ($types as $t)
                    <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="block text-xs font-medium text-slate-500 mb-1">Dari tanggal</label>
            <input id="from" name="from" type="date" value="{{ request('from') }}"
                class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        </div>
        <div>
            <label for="to" class="block text-xs font-medium text-slate-500 mb-1">Sampai tanggal</label>
            <input id="to" name="to" type="date" value="{{ request('to') }}"
                class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        </div>
        <button type="submit" class="rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-5 py-2.5 transition">Terapkan</button>
        @if (request('user') || request('type') || request('from') || request('to'))
            <a href="{{ route('admin.logs.index') }}" class="text-sm font-medium text-slate-500 hover:text-brand pb-2.5">Reset</a>
        @endif
    </form>

    <p class="mt-4 text-xs text-slate-400">{{ number_format($logs->total(), 0, ',', '.') }} entri</p>

    <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3 font-semibold">Waktu</th>
                    <th class="px-5 py-3 font-semibold">Pengguna</th>
                    <th class="px-5 py-3 font-semibold">Aktivitas</th>
                    <th class="px-5 py-3 font-semibold">Keterangan</th>
                    <th class="px-5 py-3 font-semibold">IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($logs as $log)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3 whitespace-nowrap text-xs text-slate-500">{{ optional($log->created_at)->format('d/m/Y H:i:s') }}</td>
                        <td class="px-5 py-3">
                            @if ($log->user)
                                <p class="font-medium">{{ $log->user->name }}</p>
                                <p class="text-xs text-slate-400 font-mono">{{ $log->user->nip_nik }}</p>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge[$log->activity_type] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $log->activity_type }}</span>
                        </td>
                        <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $log->description ?? '—' }}</td>
                        <td class="px-5 py-3 text-xs text-slate-400 font-mono">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="5"
                        :filtered="request()->filled('user') || request()->filled('type') || request()->filled('from') || request()->filled('to')"
                        title="Belum ada aktivitas tercatat"
                        hint="Log terisi otomatis saat pengguna login, membuka aplikasi, atau ditolak aksesnya."
                        filtered-hint="Coba ubah rentang tanggal atau jenis aktivitas, lalu tekan Terapkan." />
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
