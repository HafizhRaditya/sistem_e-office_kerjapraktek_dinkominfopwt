@extends('layouts.admin')

@section('title', 'Manajemen Kategori')
@section('heading', 'Manajemen Kategori')

@section('content')
<div class="max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola kategori yang dapat dipasang pada satu atau lebih aplikasi.</p>
            <p class="mt-1 text-xs text-slate-400">Kategori nonaktif disembunyikan dari dashboard tanpa menyembunyikan aplikasi yang terhubung.</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">
            <span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah Kategori
        </a>
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm font-medium text-brand">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('admin.categories.index') }}" class="mt-5 flex flex-wrap items-center gap-3">
        <div class="relative min-w-[16rem] flex-1">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau slug kategori…"
                class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
        </div>
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">Terapkan</button>
        @if (request()->filled('q') || request()->filled('status'))
            <a href="{{ route('admin.categories.index') }}" class="text-sm font-medium text-slate-500 hover:text-brand">Reset</a>
        @endif
    </form>

    <p class="mt-3 text-xs text-slate-400">{{ number_format($categories->total(), 0, ',', '.') }} kategori</p>

    <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full min-w-[760px] text-sm">
            <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-400 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3 font-semibold">Kategori</th>
                    <th class="px-5 py-3 font-semibold">Slug</th>
                    <th class="px-5 py-3 font-semibold">Urutan</th>
                    <th class="px-5 py-3 font-semibold">Aplikasi</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($categories as $category)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3 font-medium">{{ $category->name }}</td>
                        <td class="px-5 py-3"><span class="font-mono text-xs text-slate-500">{{ $category->slug }}</span></td>
                        <td class="px-5 py-3 text-slate-500">{{ number_format($category->sort_order, 0, ',', '.') }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ number_format($category->applications_count, 0, ',', '.') }}</td>
                        <td class="px-5 py-3">
                            @if ($category->is_active)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Aktif</span>
                            @else
                                <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('admin.categories.status', $category) }}"
                                    onsubmit="return confirm('{{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }} kategori &quot;{{ $category->name }}&quot;? Relasi aplikasi tetap disimpan.');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-200">
                                        {{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                                <a href="{{ route('admin.categories.edit', $category) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-200">
                                    <span class="material-symbols-outlined" style="font-size:16px">edit</span> Kelola
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="6"
                        :filtered="request()->filled('q') || request()->filled('status')"
                        title="Belum ada kategori"
                        hint="Tambahkan kategori agar aplikasi dapat dikelompokkan pada dashboard."
                        filtered-hint="Coba ubah kata pencarian atau filter status.">
                        <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">
                            <span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah Kategori
                        </a>
                    </x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $categories->links() }}</div>
</div>
@endsection
