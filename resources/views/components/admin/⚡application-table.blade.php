<?php

use App\Models\Application;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Manajemen Aplikasi table with live (type-as-you-go) search.
 *
 * Server-side by design: the list is paginated, so client-side filtering would
 * only search the rows currently on screen. Livewire re-runs the query and swaps
 * just this table, so it feels instant without a full page reload.
 */
new class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['q']);
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'applications' => Application::query()
                ->with('opd')
                ->withCount('links')
                ->when($this->q !== '', function ($query) {
                    $term = trim($this->q);
                    $query->where(function ($w) use ($term) {
                        $w->where('name', 'ilike', "%{$term}%")->orWhere('slug', 'ilike', "%{$term}%");
                    });
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15),
            'groupLabels' => ['smartcity' => 'Smart City', 'spbe' => 'SPBE', 'tools' => 'Tools'],
        ];
    }
};
?>

<div>
    {{-- Search: live, no Enter needed --}}
    <div class="mt-5 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 max-w-md">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Cari nama atau slug…"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 pl-10 pr-9 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
            <span wire:loading wire:target="q" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">…</span>
        </div>
        @if ($q !== '')
            <button type="button" wire:click="resetFilters" class="text-sm font-medium text-slate-500 hover:text-brand">Reset</button>
        @endif
    </div>

    <p class="mt-3 text-xs text-slate-400">
        {{ $applications->total() }} aplikasi @if ($q !== '') cocok dengan "{{ $q }}" @endif
    </p>

    <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900" wire:loading.class="opacity-60">
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
                    <tr wire:key="app-{{ $app->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
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
                    <x-admin.empty-row :colspan="6" :filtered="$q !== ''"
                        title="Belum ada aplikasi"
                        hint="Aplikasi yang ditambahkan di sini akan muncul di dashboard pegawai yang diberi hak akses.">
                        <a href="{{ route('admin.aplikasi.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-4 py-2 transition">
                            <span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah Aplikasi
                        </a>
                    </x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $applications->links() }}</div>
</div>
