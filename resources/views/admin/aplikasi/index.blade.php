@extends('layouts.admin')

@section('title', 'Manajemen Aplikasi')
@section('heading', 'Manajemen Aplikasi')

@section('content')
@php $groupLabels = ['smartcity' => 'Smart City', 'spbe' => 'SPBE', 'tools' => 'Tools']; @endphp
<div class="max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola aplikasi portal beserta tautan peluncurannya.</p>
        <a href="{{ route('admin.aplikasi.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-4 py-2.5 transition">
            <span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah Aplikasi
        </a>
    </div>

    <form method="GET" action="{{ route('admin.aplikasi.index') }}" class="mt-5 relative max-w-md">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau slug…"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 pl-10 pr-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
    </form>

    <div class="mt-5 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3 font-semibold">Aplikasi</th>
                    <th class="px-5 py-3 font-semibold">OPD</th>
                    <th class="px-5 py-3 font-semibold">Grup / Kategori</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 font-semibold text-center">Tautan</th>
                    <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($applications as $app)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3">
                            <p class="font-medium">{{ $app->name }} @if ($app->is_new)<span class="ml-1 px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300 text-[10px] font-semibold uppercase">Baru</span>@endif</p>
                            <p class="text-xs text-slate-400 font-mono">{{ $app->slug }}</p>
                        </td>
                        <td class="px-5 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ optional($app->opd)->code ?? '—' }}</span></td>
                        <td class="px-5 py-3">
                            <span class="font-medium">{{ $groupLabels[$app->app_group] ?? $app->app_group }}</span>
                            <span class="text-slate-400 capitalize">/ {{ $app->category ?? '—' }}</span>
                        </td>
                        <td class="px-5 py-3">
                            @if ($app->is_active)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Aktif</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center text-slate-500">{{ $app->links_count }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.aplikasi.edit', $app) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-brand hover:text-brand transition">
                                <span class="material-symbols-outlined" style="font-size:16px">edit</span> Kelola
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">Belum ada aplikasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $applications->links() }}</div>
</div>
@endsection
