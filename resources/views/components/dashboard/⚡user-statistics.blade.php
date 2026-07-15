<?php

use App\Models\Application;
use App\Models\ApplicationVisit;
use Livewire\Component;

new class extends Component
{
    public array $stats = [];

    public function mount(array $initialStats = []): void
    {
        $this->stats = $initialStats ?: $this->calculateStats();
    }

    public function refreshStats(): void
    {
        $this->stats = $this->calculateStats();
    }

    private function calculateStats(): array
    {
        $user = auth()->user();
        $totalApplications = Application::count();

        $accessibleApplications = $user->isAdmin()
            ? $totalApplications
            : $user->applicationAccess()->count();

        return [
            'accessible_apps' => $accessibleApplications,
            'restricted_apps' => max(
                $totalApplications - $accessibleApplications,
                0
            ),
            'month_visits' => ApplicationVisit::query()
                ->where('user_id', $user->id)
                ->where(
                    'visit_date',
                    '>=',
                    now()->startOfMonth()->toDateString()
                )
                ->count(),
            'year_visits' => ApplicationVisit::query()
                ->where('user_id', $user->id)
                ->where(
                    'visit_date',
                    '>=',
                    now()->startOfYear()->toDateString()
                )
                ->count(),
        ];
    }
};
?>

@php
    $statCards = [
        [
            'key' => 'accessible_apps',
            'label' => 'Dapat diakses',
            'description' => 'Aplikasi tersedia untuk akun Anda',
            'icon' => 'apps',
        ],
        [
            'key' => 'restricted_apps',
            'label' => 'Akses terbatas',
            'description' => 'Aplikasi yang belum dapat dibuka',
            'icon' => 'lock',
        ],
        [
            'key' => 'month_visits',
            'label' => 'Kunjungan bulan ini',
            'description' => 'Peluncuran aplikasi yang valid',
            'icon' => 'calendar_month',
        ],
        [
            'key' => 'year_visits',
            'label' => 'Kunjungan tahun ini',
            'description' => 'Total aktivitas sepanjang tahun',
            'icon' => 'monitoring',
        ],
    ];
@endphp

<section
    x-on:focus.window="$wire.refreshStats()"
    wire:loading.class="opacity-60"
>
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <p class="text-xs font-semibold uppercase text-brand">
                Ringkasan akun
            </p>
            <h2 class="mt-1 text-lg font-semibold">
                Statistik aktivitas Anda
            </h2>
        </div>

        <p class="text-xs text-slate-500 dark:text-slate-400">
            Diperbarui dari aktivitas portal
        </p>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ($statCards as $stat)
            <article class="flex min-h-32 items-start gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="grid h-10 w-10 flex-none place-items-center rounded-lg bg-red-50 text-brand dark:bg-red-950/50 dark:text-red-300">
                    <span class="material-symbols-outlined">
                        {{ $stat['icon'] }}
                    </span>
                </div>

                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-300">
                        {{ $stat['label'] }}
                    </p>

                    <p class="mt-1 text-2xl font-bold tabular-nums">
                        {{ number_format($stats[$stat['key']] ?? 0, 0, ',', '.') }}
                    </p>

                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ $stat['description'] }}
                    </p>
                </div>
            </article>
        @endforeach
    </div>
</section>
