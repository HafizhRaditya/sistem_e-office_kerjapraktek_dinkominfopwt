<?php

use App\Models\Opd;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Manajemen Pengguna table with live (type-as-you-go) search.
 *
 * The query stays SERVER-SIDE on purpose: the list is paginated, so filtering on
 * the client would only search the rows of the page currently shown. Livewire
 * re-runs the query and swaps just this table, so it feels instant without a
 * full page reload. Filter state is mirrored into the URL so it stays
 * shareable/bookmarkable, exactly like the old query-string form.
 */
new class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'opd', except: '')]
    public string $opd = '';

    #[Url(as: 'role', except: '')]
    public string $role = '';

    /** Any filter change must send the user back to page 1. */
    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingOpd(): void
    {
        $this->resetPage();
    }

    public function updatingRole(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'opd', 'role']);
        $this->resetPage();
    }

    public function with(): array
    {
        $users = User::query()
            ->with('opd')
            ->when($this->q !== '', function ($query) {
                $term = trim($this->q);
                $query->where(function ($w) use ($term) {
                    $w->where('name', 'ilike', "%{$term}%")->orWhere('nip_nik', 'ilike', "%{$term}%");
                });
            })
            ->when($this->opd !== '', fn ($query) => $query->where('opd_id', (int) $this->opd))
            ->when($this->role !== '', fn ($query) => $query->where('role', $this->role))
            ->orderBy('name')
            ->paginate(15);

        return [
            'users' => $users,
            'opds' => Opd::orderBy('name')->get(),
            'roles' => ['admin', 'pegawai'],
        ];
    }
};
?>

<div>
    {{-- Filters: live, no Enter needed --}}
    <div class="mt-5 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[16rem]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Cari nama atau NIP/NIK…"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 pl-10 pr-9 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
            <span wire:loading wire:target="q" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">…</span>
        </div>
        <select wire:model.live="opd" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="">Semua OPD</option>
            @foreach ($opds as $opd)
                <option value="{{ $opd->id }}">{{ $opd->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="role" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm capitalize focus:outline-none focus:border-brand">
            <option value="">Semua peran</option>
            @foreach ($roles as $r)
                <option value="{{ $r }}">{{ ucfirst($r) }}</option>
            @endforeach
        </select>
        @if ($q !== '' || $opd !== '' || $role !== '')
            <button type="button" wire:click="resetFilters" class="text-sm font-medium text-slate-500 hover:text-brand">Reset</button>
        @endif
    </div>

    <p class="mt-3 text-xs text-slate-400">
        {{ $users->total() }} pengguna @if ($q !== '') cocok dengan "{{ $q }}" @endif
    </p>

    <div class="mt-2 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900" wire:loading.class="opacity-60">
        <table class="w-full text-sm">
            <thead class="text-left text-xs uppercase tracking-wider text-slate-400 border-b border-slate-200 dark:border-slate-800">
                <tr>
                    <th class="px-5 py-3 font-semibold">Nama &amp; NIP/NIK</th>
                    <th class="px-5 py-3 font-semibold">OPD</th>
                    <th class="px-5 py-3 font-semibold">Peran</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                    <th class="px-5 py-3 font-semibold">Login terakhir</th>
                    <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($users as $u)
                    @php
                        $inits = \Illuminate\Support\Str::of($u->name)->explode(' ')->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->take(2)->implode('');
                        $isSelf = $u->is(auth()->user());
                    @endphp
                    <tr wire:key="user-{{ $u->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-full bg-brand/10 text-brand grid place-items-center text-xs font-bold shrink-0">{{ $inits }}</span>
                                <div class="leading-tight">
                                    <p class="font-medium">{{ $u->name }} @if ($isSelf)<span class="text-xs text-slate-400">(Anda)</span>@endif</p>
                                    <p class="text-xs text-slate-400 font-mono">{{ $u->nip_nik }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ optional($u->opd)->code ?? '—' }}</span></td>
                        <td class="px-5 py-3">
                            @if ($u->role === 'admin')
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-brand/10 text-brand">Admin</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">Pegawai</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if ($u->is_active)
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Aktif</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-400">{{ $u->last_login_at ? $u->last_login_at->format('d/m/Y H:i') : 'Belum pernah' }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @unless ($isSelf)
                                    <form method="POST" action="{{ route('admin.users.status', $u) }}"
                                        onsubmit="return confirm('{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun &quot;{{ $u->name }}&quot;?');">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-brand hover:text-brand transition">
                                            {{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                @endunless
                                <a href="{{ route('admin.users.edit', $u) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-brand hover:text-brand transition">
                                    <span class="material-symbols-outlined" style="font-size:16px">edit</span> Kelola
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="6" :filtered="$q !== '' || $opd !== '' || $role !== ''"
                        title="Belum ada pengguna"
                        hint="Tambahkan akun pegawai atau administrator untuk mulai mengatur hak akses.">
                        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-4 py-2 transition">
                            <span class="material-symbols-outlined" style="font-size:18px">person_add</span> Tambah Pengguna
                        </a>
                    </x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</div>
