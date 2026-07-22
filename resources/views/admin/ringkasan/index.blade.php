@extends('layouts.admin')

@section('title', 'Ringkasan')
@section('heading', 'Ringkasan')

@section('content')
@php
    // Percentage helper for the OPD bars; guards against division by zero when
    // no user exists yet.
    $maxOpd = $usersByOpd->max('total') ?: 1;
@endphp

<div class="max-w-6xl space-y-8">
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Ringkasan modul Autentikasi &amp; Kontrol Akses: pengguna, hak akses, dan aplikasi portal.
        Statistik partisipasi kuisioner ada pada halaman <span class="font-medium">Statistik Kuisioner</span>.
    </p>

    {{-- ============ Kartu metrik utama ============ --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $cards = [
                [
                    'label' => 'Total Pengguna',
                    'value' => $users['total'],
                    'icon' => 'group',
                    'detail' => $users['admins'].' admin · '.$users['pegawai'].' pegawai',
                    'href' => route('admin.users.index'),
                ],
                [
                    'label' => 'Pengguna Aktif',
                    'value' => $users['active'],
                    'icon' => 'how_to_reg',
                    'detail' => $users['inactive'].' nonaktif',
                    'href' => route('admin.users.index', ['q' => '']),
                ],
                [
                    'label' => 'Aplikasi Portal',
                    'value' => $applications['total'],
                    'icon' => 'apps',
                    'detail' => $applications['active'].' aktif · '.$applications['inactive'].' nonaktif',
                    'href' => route('admin.aplikasi.index'),
                ],
                [
                    'label' => 'Hak Akses Terpasang',
                    'value' => $access['grants'],
                    'icon' => 'key',
                    'detail' => $access['pegawai_with_access'].' pegawai punya akses',
                    'href' => route('admin.akses.index'),
                ],
            ];
        @endphp

        @foreach ($cards as $card)
            <a href="{{ $card['href'] }}"
                class="group rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 hover:border-brand/60 transition">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ $card['label'] }}</p>
                    <span class="material-symbols-outlined text-slate-300 dark:text-slate-600 group-hover:text-brand transition" style="font-size:22px" aria-hidden="true">{{ $card['icon'] }}</span>
                </div>
                <p class="mt-2 text-3xl font-bold tracking-tight">{{ number_format($card['value'], 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $card['detail'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- ============ Sebaran pengguna per OPD ============ --}}
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold">Sebaran Pengguna per OPD</h2>
                <a href="{{ route('admin.users.index') }}" class="text-xs font-medium text-slate-500 hover:text-brand">Kelola →</a>
            </div>

            <div class="mt-5 space-y-3">
                @forelse ($usersByOpd as $opd)
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium">{{ $opd->code }}</span>
                            <span class="text-slate-400 text-xs truncate flex-1 mx-3">{{ $opd->name }}</span>
                            <span class="font-semibold tabular-nums">{{ $opd->total }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div class="h-full rounded-full bg-brand" style="width: {{ round($opd->total / $maxOpd * 100) }}%"></div>
                        </div>
                        @if ($opd->total > $opd->active)
                            <p class="mt-1 text-[11px] text-slate-400">{{ $opd->total - $opd->active }} nonaktif</p>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Belum ada OPD terdaftar.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            {{-- ============ Aplikasi & tautan ============ --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">Aplikasi &amp; Tautan</h2>
                    <a href="{{ route('admin.aplikasi.index') }}" class="text-xs font-medium text-slate-500 hover:text-brand">Kelola →</a>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-slate-400">Aplikasi aktif</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums">{{ $applications['active'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Aplikasi nonaktif</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums {{ $applications['inactive'] > 0 ? 'text-brand' : '' }}">{{ $applications['inactive'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Total tautan</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums">{{ $applications['links'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Tautan aktif</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums">{{ $applications['links_active'] }}</dd>
                    </div>
                </dl>

                @if ($applications['inactive'] > 0)
                    <p class="mt-4 text-xs text-slate-400">
                        Aplikasi nonaktif tidak dapat diluncurkan siapa pun, termasuk admin.
                    </p>
                @endif
            </div>

            {{-- ============ Cakupan hak akses ============ --}}
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">Cakupan Hak Akses</h2>
                    <a href="{{ route('admin.akses.index') }}" class="text-xs font-medium text-slate-500 hover:text-brand">Atur →</a>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-slate-400">Pegawai punya akses</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums">{{ $access['pegawai_with_access'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Belum punya akses</dt>
                        <dd class="mt-0.5 text-xl font-bold tabular-nums {{ $access['pegawai_without_access'] > 0 ? 'text-brand' : '' }}">{{ $access['pegawai_without_access'] }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-xs text-slate-400">
                    Admin tidak memerlukan baris hak akses — peran admin melewati pemeriksaan izin.
                </p>
            </div>
        </div>
    </div>

    {{-- ============ Login terbaru ============ --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <div class="flex items-center justify-between gap-3 px-6 py-6">
            <h2 class="text-base font-semibold">{{ count($recentLogins) }} Login Terakhir</h2>
            <a href="{{ route('admin.logs.index') }}" class="text-xs font-medium text-slate-500 hover:text-brand">Lihat semua aktivitas →</a>
        </div>

        <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-800">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wider text-slate-400 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Pengguna</th>
                        <th class="px-6 py-3 font-semibold">Waktu</th>
                        <th class="px-6 py-3 font-semibold">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($recentLogins as $log)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-6 py-3">
                                @if ($log->user)
                                    <p class="font-medium">{{ $log->user->name }}</p>
                                    <p class="text-xs text-slate-400 font-mono">{{ $log->user->nip_nik }}</p>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 whitespace-nowrap text-xs text-slate-500">{{ optional($log->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td class="px-6 py-3 text-xs text-slate-400 font-mono">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-admin.empty-row :colspan="3"
                            title="Belum ada login tercatat"
                            hint="Baris terisi otomatis setiap pengguna berhasil masuk ke portal." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
