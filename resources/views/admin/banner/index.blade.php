@extends('layouts.admin')

@section('title', 'Manajemen Banner')
@section('heading', 'Manajemen Banner')

@section('content')
<div class="max-w-6xl space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola banner hero dan popup informasi pada dashboard pegawai.</p>
            <p class="mt-1 text-xs text-slate-400">Banner aktif tetap mengikuti periode mulai dan selesai yang ditentukan.</p>
        </div>
        <a href="{{ route('admin.banners.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-4 py-2.5 transition">
            <span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah Banner
        </a>
    </div>

    <form method="GET" action="{{ route('admin.banners.index') }}" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
        <div class="grid gap-3 sm:grid-cols-[1fr_220px_auto] sm:items-end">
            <div>
                <label for="search" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Pencarian</label>
                <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Judul atau deskripsi banner"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
            </div>
            <div>
                <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2.5 text-sm focus:outline-none focus:border-brand">
                    <option value="all" @selected($status === 'all')>Semua status</option>
                    <option value="active" @selected($status === 'active')>Sedang tayang</option>
                    <option value="scheduled" @selected($status === 'scheduled')>Terjadwal</option>
                    <option value="expired" @selected($status === 'expired')>Sudah berakhir</option>
                    <option value="inactive" @selected($status === 'inactive')>Dinonaktifkan</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-slate-900 dark:bg-slate-700 text-white text-sm font-semibold px-4 py-2.5 hover:bg-slate-700 transition">Terapkan</button>
                <a href="{{ route('admin.banners.index') }}" class="rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:border-brand hover:text-brand transition">Reset</a>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[880px] text-sm">
                <thead class="bg-slate-50 dark:bg-slate-800/60 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Banner</th>
                        <th class="px-4 py-3 font-semibold">Periode</th>
                        <th class="px-4 py-3 font-semibold">Urutan</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($banners as $banner)
                        @php
                            $now = now();
                            $currentlyActive = $banner->is_active
                                && (! $banner->starts_at || $banner->starts_at->lte($now))
                                && (! $banner->ends_at || $banner->ends_at->gte($now));
                            $scheduled = $banner->is_active && $banner->starts_at?->gt($now);
                            $expired = $banner->ends_at?->lt($now);

                            [$statusLabel, $statusClass] = match (true) {
                                ! $banner->is_active => ['Nonaktif', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
                                $scheduled => ['Terjadwal', 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300'],
                                $expired => ['Berakhir', 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
                                $currentlyActive => ['Sedang tayang', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
                                default => ['Aktif', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
                            };
                        @endphp
                        <tr class="align-middle hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-14 w-24 shrink-0 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800">
                                        @if ($banner->image_path)
                                            <img src="{{ preg_match('/^https?:\/\//i', $banner->image_path) ? $banner->image_path : asset(ltrim($banner->image_path, '/')) }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            <div class="grid h-full place-items-center text-slate-400">
                                                <span class="material-symbols-outlined">image</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ $banner->title }}</p>
                                        <p class="mt-0.5 max-w-md truncate text-xs text-slate-500">{{ $banner->description ?: 'Tanpa deskripsi' }}</p>
                                        <p class="mt-1 text-[11px] text-slate-400">Dibuat oleh {{ $banner->creator?->name ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                <div>Mulai: {{ $banner->starts_at?->format('d M Y H:i') ?? 'langsung' }}</div>
                                <div>Selesai: {{ $banner->ends_at?->format('d M Y H:i') ?? 'tanpa batas' }}</div>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ $banner->sort_order }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.banners.edit', $banner) }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-brand dark:text-slate-300 dark:hover:bg-slate-800" title="Ubah banner">
                                        <span class="material-symbols-outlined" style="font-size:17px">edit</span> Ubah
                                    </a>
                                    <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" onsubmit="return confirm('Hapus banner &quot;{{ $banner->title }}&quot;? Tindakan ini tidak dapat dibatalkan.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-red-50 hover:text-brand dark:hover:bg-red-900/20" title="Hapus banner">
                                            <span class="material-symbols-outlined" style="font-size:17px">delete</span> Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-slate-500">
                                Tidak ada banner yang sesuai dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($banners->hasPages())
            <div class="border-t border-slate-200 dark:border-slate-800 px-4 py-3">
                {{ $banners->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
