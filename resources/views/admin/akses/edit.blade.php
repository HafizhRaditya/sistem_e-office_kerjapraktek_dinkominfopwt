@extends('layouts.admin')

@section('title', 'Atur Akses — '.$user->name)
@section('heading', 'Atur Hak Akses')

@section('content')
@php
    $inits = \Illuminate\Support\Str::of($user->name)->explode(' ')->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->take(2)->implode('');
@endphp

<div class="max-w-5xl"
    x-data="{
        apps: @js($apps),
        initial: @js($grantedIds),
        selected: @js($grantedIds),
        q: '',
        opd: 'all',
        cat: 'all',
        isOn(id) { return this.selected.includes(id); },
        matches(a) {
            const q = this.q.toLowerCase();
            return (this.opd === 'all' || a.opd === this.opd)
                && (this.cat === 'all' || a.category === this.cat)
                && ((a.name + ' ' + (a.opd || '')).toLowerCase().includes(q));
        },
        get visibleCount() { return this.apps.filter(a => this.matches(a)).length; },
        get changes() {
            const s = new Set(this.selected.map(Number));
            const i = new Set(this.initial.map(Number));
            return this.apps.filter(a => s.has(a.id) !== i.has(a.id)).length;
        },
    }">

    <a href="{{ route('admin.akses.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke daftar
    </a>

    {{-- Profile card --}}
    <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 flex flex-wrap items-center gap-4">
        <span class="w-14 h-14 rounded-full bg-brand text-white grid place-items-center text-lg font-bold">{{ $inits }}</span>
        <div class="min-w-0">
            <p class="text-lg font-semibold leading-tight">{{ $user->name }}</p>
            <p class="text-sm text-slate-400 font-mono">{{ $user->nip_nik }}</p>
            <div class="mt-1 flex items-center gap-2">
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ optional($user->opd)->name ?? '—' }}</span>
                @if ($user->role === 'admin')
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-brand/10 text-brand">Admin</span>
                @else
                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">Pegawai</span>
                @endif
            </div>
        </div>
        <div class="ml-auto text-right">
            @if ($user->role === 'admin')
                <p class="text-2xl font-bold text-brand">Semua</p>
                <p class="text-xs text-slate-400">akses (bypass)</p>
            @else
                <p class="text-2xl font-bold"><span x-text="selected.length">{{ $grantedIds->count() }}</span> <span class="text-base font-normal text-slate-400">diakses dari {{ $totalApps }} total</span></p>
            @endif
        </div>
    </div>

    @if ($user->role === 'admin')
        <div class="mt-5 rounded-xl border border-brand/30 bg-brand/5 p-5 text-sm text-slate-600 dark:text-slate-300">
            <span class="material-symbols-outlined align-middle text-brand" style="font-size:20px">verified_user</span>
            Pengguna ini adalah <strong>Admin</strong> — memiliki akses ke semua aplikasi secara bawaan (bypass), jadi tidak perlu diatur per aplikasi.
        </div>
    @else
        <form method="POST" action="{{ route('admin.akses.update', $user) }}" class="mt-5">
            @csrf
            @method('PUT')

            {{-- Filters (client-side, Alpine) --}}
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[14rem]">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                    <input type="search" x-model="q" placeholder="Cari aplikasi…"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 pl-10 pr-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
                </div>
                <select x-model="opd" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
                    <option value="all">Semua OPD pemilik</option>
                    @foreach ($opds as $o)
                        <option value="{{ $o->code }}">{{ $o->name }}</option>
                    @endforeach
                </select>
                <select x-model="cat" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 capitalize">
                    <option value="all">Semua kategori</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Application list with toggles (all loaded; filtered client-side with x-show so
                 every checkbox stays in the DOM and submits correctly) --}}
            <div class="mt-4 space-y-2 pb-24">
                <template x-for="a in apps" :key="a.id">
                    <label x-show="matches(a)"
                        class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3 cursor-pointer hover:border-slate-300 dark:hover:border-slate-600 transition">
                        <div class="min-w-0">
                            <p class="font-medium truncate" x-text="a.name"></p>
                            <p class="text-xs text-slate-400">
                                <span x-text="a.opd"></span> · <span class="capitalize" x-text="a.category"></span>
                                <span x-show="!a.active" class="text-red-500"> · Nonaktif</span>
                            </p>
                        </div>
                        <input type="checkbox" name="access[]" :value="a.id" x-model.number="selected" class="sr-only">
                        <span class="relative shrink-0 w-11 h-6 rounded-full transition"
                            :class="isOn(a.id) ? 'bg-brand' : 'bg-slate-300 dark:bg-slate-600'">
                            <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition"
                                :class="isOn(a.id) ? 'translate-x-5' : ''"></span>
                        </span>
                    </label>
                </template>
                <p x-show="visibleCount === 0" class="py-8 text-center text-sm text-slate-500">Tidak ada aplikasi yang cocok dengan filter.</p>
            </div>

            {{-- Sticky save bar --}}
            <div class="sticky bottom-0 -mx-5 sm:-mx-8 px-5 sm:px-8 py-3 bg-white/90 dark:bg-slate-900/90 backdrop-blur border-t border-slate-200 dark:border-slate-800 flex items-center gap-4">
                <p class="text-sm">
                    <template x-if="changes > 0">
                        <span class="font-semibold text-brand"><span x-text="changes"></span> perubahan belum disimpan</span>
                    </template>
                    <template x-if="changes === 0">
                        <span class="text-slate-400">Tidak ada perubahan</span>
                    </template>
                </p>
                <button type="submit" :disabled="changes === 0"
                    class="ml-auto rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-5 py-2.5 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
