@extends('layouts.admin')

@section('title', 'Statistik Kuisioner')
@section('heading', 'Statistik Kuisioner')

@section('content')
<div class="max-w-7xl" x-data="{ tab: @js(request('tab', 'summary')) }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pantau partisipasi pegawai aktif pada setiap kuisioner.</p>
            <p class="mt-1 text-xs text-slate-400">Angka partisipasi menunjukkan klik tautan kuisioner, bukan konfirmasi pengiriman formulir.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($selected)
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $selected->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                    {{ $selected->is_active ? 'Kuisioner aktif' : 'Kuisioner tidak aktif' }}
                </span>
                <a href="{{ route('admin.questionnaires.edit', $selected) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-300">
                    <span class="material-symbols-outlined" style="font-size:16px">edit</span> Ubah
                </a>
            @endif
            <a href="{{ route('admin.questionnaires.index') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-300">
                <span class="material-symbols-outlined" style="font-size:16px">list</span> Kelola
            </a>
        </div>
    </div>

    @if ($questionnaires->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center dark:border-slate-700 dark:bg-slate-900">
            <span class="material-symbols-outlined text-4xl text-slate-300 dark:text-slate-600">poll</span>
            <p class="mt-3 font-semibold">Belum ada kuisioner</p>
            <p class="mt-1 text-sm text-slate-500">Buat kuisioner terlebih dahulu untuk mulai memantau partisipasi.</p>
            <a href="{{ route('admin.questionnaires.create') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-branddark">
                <span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah Kuisioner
            </a>
        </div>
    @else
        <form method="GET" action="{{ route('admin.questionnaires.statistics') }}" class="mt-4 flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" :value="tab">
            <div class="min-w-[16rem] flex-1">
                <label for="questionnaire" class="mb-1 block text-xs font-medium text-slate-500">Kuisioner</label>
                <select id="questionnaire" name="questionnaire" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-900">
                    @foreach ($questionnaires as $questionnaire)
                        <option value="{{ $questionnaire->id }}" @selected($selected?->id === $questionnaire->id)>
                            {{ $questionnaire->title }} ({{ number_format($questionnaire->responses_count, 0, ',', '.') }} klik)
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">Tampilkan</button>
        </form>

        @if ($selected)
            @if ($selected->description)
                <p class="mt-2 truncate text-xs text-slate-400" title="{{ $selected->description }}">{{ $selected->description }}</p>
            @endif

            <div class="mt-5 flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-800" role="tablist" aria-label="Bagian statistik kuisioner">
                @foreach ([
                    ['key' => 'summary', 'label' => 'Ringkasan', 'icon' => 'monitoring'],
                    ['key' => 'opd', 'label' => 'Per OPD', 'icon' => 'corporate_fare'],
                    ['key' => 'employees', 'label' => 'Daftar Pegawai', 'icon' => 'groups'],
                ] as $tabItem)
                    <button type="button" @click="tab = '{{ $tabItem['key'] }}'"
                        :aria-selected="tab === '{{ $tabItem['key'] }}'"
                        class="-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition"
                        :class="tab === '{{ $tabItem['key'] }}' ? 'border-brand text-brand' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'">
                        <span class="material-symbols-outlined" style="font-size:18px">{{ $tabItem['icon'] }}</span>
                        {{ $tabItem['label'] }}
                    </button>
                @endforeach
            </div>

            <section x-show="tab === 'summary'" x-cloak class="pt-5" role="tabpanel">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @php
                        $cards = [
                            ['label' => 'Pegawai aktif', 'value' => $metrics['total_target'], 'note' => 'Target partisipasi', 'icon' => 'group', 'class' => 'bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'],
                            ['label' => 'Sudah klik', 'value' => $metrics['active_responded'], 'note' => 'Pegawai aktif', 'icon' => 'task_alt', 'class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
                            ['label' => 'Belum klik', 'value' => $metrics['active_pending'], 'note' => 'Perlu tindak lanjut', 'icon' => 'pending_actions', 'class' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
                            ['label' => 'Partisipasi', 'value' => number_format($metrics['percentage'], 1, ',', '').'%', 'note' => number_format($metrics['total_clicks'], 0, ',', '.').' klik historis', 'icon' => 'percent', 'class' => 'bg-brand/10 text-brand'],
                        ];
                    @endphp
                    @foreach ($cards as $card)
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                                    <p class="mt-2 text-2xl font-bold tracking-tight">{{ is_numeric($card['value']) ? number_format($card['value'], 0, ',', '.') : $card['value'] }}</p>
                                    <p class="mt-1 text-[11px] text-slate-400">{{ $card['note'] }}</p>
                                </div>
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $card['class'] }}">
                                    <span class="material-symbols-outlined" style="font-size:19px">{{ $card['icon'] }}</span>
                                </span>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-medium">Progres partisipasi</span>
                        <span class="font-semibold text-brand">{{ number_format($metrics['percentage'], 1, ',', '') }}%</span>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $metrics['percentage'] }}%"></div>
                    </div>
                </div>
            </section>

            <section x-show="tab === 'opd'" x-cloak class="pt-5" role="tabpanel">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="font-semibold tracking-tight">Rekap per OPD</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Persentase dihitung dari pegawai aktif di masing-masing OPD.</p>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-400 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3 font-semibold">OPD</th>
                                <th class="px-5 py-3 text-right font-semibold">Pegawai</th>
                                <th class="px-5 py-3 text-right font-semibold">Sudah</th>
                                <th class="px-5 py-3 text-right font-semibold">Belum</th>
                                <th class="px-5 py-3 font-semibold">Partisipasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($opdStats as $stat)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-5 py-3">
                                        <p class="font-medium">{{ $stat['opd']->name }}</p>
                                        <p class="text-xs text-slate-400">{{ $stat['opd']->code }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-right">{{ $stat['total'] }}</td>
                                    <td class="px-5 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-300">{{ $stat['responded'] }}</td>
                                    <td class="px-5 py-3 text-right text-amber-600 dark:text-amber-300">{{ $stat['pending'] }}</td>
                                    <td class="min-w-[10rem] px-5 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                                <div class="h-full rounded-full bg-brand" style="width: {{ $stat['percentage'] }}%"></div>
                                            </div>
                                            <span class="w-12 text-right text-xs font-semibold">{{ number_format($stat['percentage'], 1, ',', '') }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section x-show="tab === 'employees'" x-cloak class="pt-5" role="tabpanel">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="font-semibold tracking-tight">Daftar partisipasi pegawai aktif</h2>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Cari pegawai yang sudah atau belum membuka kuisioner.</p>
                    </div>
                    <p class="text-xs text-slate-400">{{ number_format($employees->total(), 0, ',', '.') }} data</p>
                </div>

                <form method="GET" action="{{ route('admin.questionnaires.statistics') }}" class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="questionnaire" value="{{ $selected->id }}">
                    <input type="hidden" name="tab" value="employees">
                    <div>
                        <label for="status" class="mb-1 block text-xs font-medium text-slate-500">Status</label>
                        <select id="status" name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-900">
                            <option value="all" @selected(request('status', 'all') === 'all')>Semua</option>
                            <option value="responded" @selected(request('status') === 'responded')>Sudah klik</option>
                            <option value="pending" @selected(request('status') === 'pending')>Belum klik</option>
                        </select>
                    </div>
                    <div>
                        <label for="opd" class="mb-1 block text-xs font-medium text-slate-500">OPD</label>
                        <select id="opd" name="opd" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-900">
                            <option value="">Semua OPD</option>
                            @foreach ($opds as $opd)
                                <option value="{{ $opd->id }}" @selected((string) request('opd') === (string) $opd->id)>{{ $opd->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-[15rem] flex-1">
                        <label for="q" class="mb-1 block text-xs font-medium text-slate-500">Cari pegawai</label>
                        <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Nama atau NIP/NIK"
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none dark:border-slate-700 dark:bg-slate-900">
                    </div>
                    <button type="submit" class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">Terapkan</button>
                </form>

                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <table class="w-full text-sm">
                        <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-400 dark:border-slate-800">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Pegawai</th>
                                <th class="px-5 py-3 font-semibold">OPD</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($employees as $employee)
                                @php($hasResponded = $respondedIds->has($employee->id))
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-5 py-3">
                                        <p class="font-medium">{{ $employee->name }}</p>
                                        <p class="font-mono text-xs text-slate-400">{{ $employee->nip_nik }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $employee->opd->code }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $hasResponded ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }}">
                                            {{ $hasResponded ? 'Sudah klik' : 'Belum klik' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-10 text-center text-sm text-slate-500">Tidak ada pegawai yang cocok dengan filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($employees->hasPages())
                    <div class="mt-4">{{ $employees->links() }}</div>
                @endif
            </section>
        @endif
    @endif
</div>
@endsection
