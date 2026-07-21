@extends('layouts.admin')

@section('title', 'Manajemen Kuisioner')
@section('heading', 'Manajemen Kuisioner')

@section('content')
<div class="max-w-7xl space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola popup kuisioner dan tautan formulir yang ditampilkan kepada pegawai.</p>
            <p class="mt-1 text-xs text-slate-400">Sistem mencatat klik tautan, bukan konfirmasi bahwa formulir telah dikirim.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.questionnaires.statistics') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-300">
                <span class="material-symbols-outlined" style="font-size:18px">monitoring</span> Statistik
            </a>
            <a href="{{ route('admin.questionnaires.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">
                <span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah Kuisioner
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm font-medium text-brand">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="GET" action="{{ route('admin.questionnaires.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-3 sm:grid-cols-[1fr_220px_auto] sm:items-end">
            <div>
                <label for="search" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Pencarian</label>
                <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Judul, deskripsi, atau tautan formulir"
                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-950">
            </div>
            <div>
                <label for="status" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-950">
                    <option value="all" @selected($status === 'all')>Semua status</option>
                    <option value="active" @selected($status === 'active')>Sedang tayang</option>
                    <option value="scheduled" @selected($status === 'scheduled')>Terjadwal</option>
                    <option value="expired" @selected($status === 'expired')>Sudah berakhir</option>
                    <option value="inactive" @selected($status === 'inactive')>Dinonaktifkan</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-700">Terapkan</button>
                <a href="{{ route('admin.questionnaires.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-300">Reset</a>
            </div>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Kuisioner</th>
                        <th class="px-4 py-3 font-semibold">Periode</th>
                        <th class="px-4 py-3 text-center font-semibold">Respons</th>
                        <th class="px-4 py-3 font-semibold">Urutan</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($questionnaires as $questionnaire)
                        @php
                            $now = now();
                            $currentlyActive = $questionnaire->is_active
                                && (! $questionnaire->starts_at || $questionnaire->starts_at->lte($now))
                                && (! $questionnaire->ends_at || $questionnaire->ends_at->gte($now));
                            $scheduled = $questionnaire->is_active && $questionnaire->starts_at?->gt($now);
                            $expired = $questionnaire->ends_at?->lt($now);

                            [$statusLabel, $statusClass] = match (true) {
                                ! $questionnaire->is_active => ['Nonaktif', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
                                $scheduled => ['Terjadwal', 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300'],
                                $expired => ['Berakhir', 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
                                $currentlyActive => ['Sedang tayang', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
                                default => ['Aktif', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'],
                            };
                        @endphp
                        <tr class="align-middle hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="h-14 w-24 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-800">
                                        @if ($questionnaire->banner_image)
                                            <img src="{{ preg_match('/^https?:\/\//i', $questionnaire->banner_image) ? $questionnaire->banner_image : asset(ltrim($questionnaire->banner_image, '/')) }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            <div class="grid h-full place-items-center text-slate-400"><span class="material-symbols-outlined">quiz</span></div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ $questionnaire->title }}</p>
                                        <p class="mt-0.5 max-w-md truncate text-xs text-slate-500">{{ $questionnaire->description ?: 'Tanpa deskripsi' }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400">
                                            <span>Dibuat oleh {{ $questionnaire->creator?->name ?? '—' }}</span>
                                            <a href="{{ $questionnaire->target_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-0.5 hover:text-brand">
                                                Buka formulir <span class="material-symbols-outlined" style="font-size:13px">open_in_new</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                <div>Mulai: {{ $questionnaire->starts_at?->format('d M Y H:i') ?? 'langsung' }}</div>
                                <div>Selesai: {{ $questionnaire->ends_at?->format('d M Y H:i') ?? 'tanpa batas' }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a href="{{ route('admin.questionnaires.statistics', ['questionnaire' => $questionnaire->id]) }}" class="inline-flex min-w-12 justify-center rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-100 dark:bg-sky-900/30 dark:text-sky-300">
                                    {{ number_format($questionnaire->responses_count, 0, ',', '.') }}
                                </a>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ $questionnaire->sort_order }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.questionnaires.edit', $questionnaire) }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-brand dark:text-slate-300 dark:hover:bg-slate-800" title="Ubah kuisioner">
                                        <span class="material-symbols-outlined" style="font-size:17px">edit</span> Ubah
                                    </a>
                                    @if ((int) $questionnaire->responses_count === 0)
                                        <form method="POST" action="{{ route('admin.questionnaires.destroy', $questionnaire) }}" onsubmit="return confirm('Hapus kuisioner &quot;{{ $questionnaire->title }}&quot;? Tindakan ini tidak dapat dibatalkan.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-red-50 hover:text-brand dark:hover:bg-red-900/20" title="Hapus kuisioner">
                                                <span class="material-symbols-outlined" style="font-size:17px">delete</span> Hapus
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" disabled class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg px-3 py-2 text-xs font-semibold text-slate-300 dark:text-slate-600" title="Tidak dapat dihapus karena sudah memiliki respons">
                                            <span class="material-symbols-outlined" style="font-size:17px">lock</span> Hapus
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">Tidak ada kuisioner yang sesuai dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($questionnaires->hasPages())
            <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $questionnaires->links() }}</div>
        @endif
    </div>
</div>
@endsection
