@extends('layouts.admin')

@section('title', 'Manajemen Hak Akses')
@section('heading', 'Manajemen Hak Akses')

@section('content')
<div class="max-w-6xl">
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Atur aplikasi yang dapat diakses tiap pegawai. Admin memiliki akses ke semua aplikasi (bypass).
    </p>

    {{-- Search (name / NIP-NIK) + OPD filter — server-side because the list is paginated --}}
    <form method="GET" action="{{ route('admin.akses.index') }}" class="mt-5 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[16rem]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau NIP/NIK…"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 pl-10 pr-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
        </div>
        <select name="opd" onchange="this.form.submit()"
            class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="">Semua OPD</option>
            @foreach ($opds as $opd)
                <option value="{{ $opd->id }}" @selected((string) request('opd') === (string) $opd->id)>{{ $opd->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-4 py-2.5 transition">Cari</button>
        @if (request('q') || request('opd'))
            <a href="{{ route('admin.akses.index') }}" class="text-sm font-medium text-slate-500 hover:text-brand">Reset</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3 font-semibold">Nama &amp; NIP/NIK</th>
                    <th class="px-5 py-3 font-semibold">OPD</th>
                    <th class="px-5 py-3 font-semibold">Peran</th>
                    <th class="px-5 py-3 font-semibold">Akses Aplikasi</th>
                    <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($users as $u)
                    @php
                        $inits = \Illuminate\Support\Str::of($u->name)->explode(' ')->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->take(2)->implode('');
                    @endphp
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-brand/10 text-brand grid place-items-center text-xs font-bold shrink-0">{{ $inits }}</span>
                                <div class="leading-tight">
                                    <p class="font-medium">{{ $u->name }}</p>
                                    <p class="text-xs text-slate-400 font-mono">{{ $u->nip_nik }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ optional($u->opd)->code ?? '—' }}</span>
                        </td>
                        <td class="px-5 py-3">
                            @if ($u->role === 'admin')
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-brand/10 text-brand">Admin</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">Pegawai</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if ($u->role === 'admin')
                                <span class="text-slate-500 dark:text-slate-400">Semua <span class="text-xs">(bypass)</span></span>
                            @else
                                <span class="font-medium">{{ $accessCounts[$u->id] ?? 0 }}</span>
                                <span class="text-slate-400">dari {{ $totalApps }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.akses.edit', $u) }}"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-brand hover:text-brand transition">
                                <span class="material-symbols-outlined" style="font-size:16px">tune</span> Atur Akses
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">Tidak ada pengguna yang cocok dengan pencarian.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>
@endsection
