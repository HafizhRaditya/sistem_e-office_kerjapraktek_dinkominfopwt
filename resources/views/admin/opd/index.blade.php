@extends('layouts.admin')

@section('title', 'Manajemen OPD')
@section('heading', 'Manajemen OPD')

@section('content')
<div class="max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola master Organisasi Perangkat Daerah yang digunakan oleh pengguna dan aplikasi.</p>
            <p class="mt-1 text-xs text-slate-400">OPD tidak dihapus permanen agar relasi dan histori tetap utuh.</p>
        </div>
        <a href="{{ route('admin.opds.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">
            <span class="material-symbols-outlined" style="font-size:18px">add_business</span> Tambah OPD
        </a>
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm font-medium text-brand">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('admin.opds.index') }}" class="mt-5 flex flex-wrap items-center gap-3">
        <div class="relative min-w-[16rem] flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari kode atau nama OPD…"
                class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">Terapkan</button>
        @if (request()->filled('q') || request()->filled('status'))
            <a href="{{ route('admin.opds.index') }}" class="text-sm font-medium text-slate-500 hover:text-brand">Reset</a>
        @endif
    </form>

    <p class="mt-3 text-xs text-slate-400">{{ number_format($opds->total(), 0, ',', '.') }} OPD</p>

    <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full min-w-[850px] text-sm">
            <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-400 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3 font-semibold">Kode</th>
                    <th class="px-5 py-3 font-semibold">Nama OPD</th>
                    <th class="px-5 py-3 font-semibold">Pengguna</th>
                    <th class="px-5 py-3 font-semibold">Aplikasi</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($opds as $opd)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3"><span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $opd->code }}</span></td>
                        <td class="px-5 py-3 font-medium">{{ $opd->name }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ number_format($opd->users_count, 0, ',', '.') }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ number_format($opd->applications_count, 0, ',', '.') }}</td>
                        <td class="px-5 py-3">
                            @if ($opd->is_active)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Aktif</span>
                            @else
                                <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.opds.status', $opd) }}"
                                    onsubmit="return confirm('{{ $opd->is_active ? 'Nonaktifkan' : 'Aktifkan' }} OPD &quot;{{ $opd->name }}&quot;? Relasi pengguna, aplikasi, dan histori tetap disimpan.');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-200">
                                        {{ $opd->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.opds.edit', $opd) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-200">
                                    <span class="material-symbols-outlined" style="font-size:16px">edit</span> Kelola
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="6"
                        :filtered="request()->filled('q') || request()->filled('status')"
                        title="Belum ada OPD"
                        hint="Tambahkan OPD agar dapat dipakai pada data pengguna dan aplikasi."
                        filtered-hint="Coba ubah kata pencarian atau filter status.">
                        <a href="{{ route('admin.opds.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">
                            <span class="material-symbols-outlined" style="font-size:18px">add_business</span> Tambah OPD
                        </a>
                    </x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $opds->links() }}</div>
</div>
@endsection
