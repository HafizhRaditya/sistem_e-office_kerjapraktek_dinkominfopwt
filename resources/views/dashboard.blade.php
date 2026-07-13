@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div x-data="dashboard(@js($apps))">

    {{-- ======= Hero (red brand surface) ======= --}}
    <section class="bg-gradient-to-br from-branddark via-brand to-branddark text-white">
        <div class="max-w-7xl mx-auto px-6 pt-14 pb-16 text-center">
            <p class="text-[11px] font-semibold tracking-[0.2em] uppercase text-red-200">Sistem Pemerintahan Berbasis Elektronik</p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight">E-Office Kabupaten Banyumas</h1>
            <p class="mt-4 max-w-2xl mx-auto text-red-50/90 text-sm sm:text-base leading-relaxed">
                Portal aplikasi terintegrasi bagi user OPD — seluruh aplikasi ditampilkan;
                aplikasi di luar hak akses Anda ditandai dan tidak dapat dibuka.
            </p>
            <a href="#apps" class="mt-7 inline-flex items-center gap-2 rounded-lg bg-white text-brand hover:bg-red-50 px-6 py-3 text-sm font-semibold transition shadow-sm">
                Mulai eksplorasi aplikasi <span class="material-symbols-outlined" style="font-size:18px">arrow_forward</span>
            </a>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-6 pb-20">

        {{-- ======= Aplikasi paling sering diakses ======= --}}
        <section class="mt-12">
            <h2 class="text-lg font-semibold tracking-tight">Aplikasi paling sering diakses</h2>
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <template x-for="a in topApps" :key="'top-' + a.id">
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 text-center hover:border-brand/50 transition"
                        :class="a.can_access ? '' : 'opacity-60'">
                        <p class="text-[10px] font-semibold tracking-widest uppercase text-slate-400" x-text="a.opd"></p>
                        <p class="mt-1.5 text-sm font-semibold leading-tight flex items-center justify-center gap-1">
                            <span x-show="!a.can_access" class="material-symbols-outlined" style="font-size:13px">lock</span>
                            <span x-text="a.name"></span>
                        </p>
                        <p class="mt-1 text-[11px] text-slate-400" x-text="fmt(a.month_visits) + ' / bln'"></p>
                    </div>
                </template>
            </div>
        </section>

        {{-- ======= Katalog ======= --}}
        <section id="apps" class="mt-14">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-lg font-semibold tracking-tight">Aplikasi Kabupaten Banyumas</h2>
                <div class="flex flex-wrap gap-2 text-sm">
                    <template x-for="t in [['all','Semua'],['smartcity','Smart City'],['spbe','SPBE'],['tools','Tools'],['baru','Aplikasi baru']]" :key="'tab-' + t[0]">
                        <button @click="tab = t[0]" class="px-3 py-1.5 rounded-full text-xs font-medium border transition"
                            :class="tab === t[0] ? 'bg-brand text-white border-brand' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-400'">
                            <span x-text="t[1] + ' (' + countGroup(t[0]) + ')'"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Status chips --}}
            <div class="mt-5 flex flex-wrap gap-2">
                <template x-for="s in [['all','Semua'],['on','Aktif'],['off','Tidak aktif']]" :key="'st-' + s[0]">
                    <button @click="status = s[0]" class="px-3 py-1.5 rounded-full text-xs font-medium border transition"
                        :class="status === s[0] ? 'bg-brand text-white border-brand' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-400'">
                        <span x-text="s[1] + ' (' + countStatus(s[0]) + ')'"></span>
                    </button>
                </template>
            </div>

            {{-- Access chips (RBAC marker filter) --}}
            <div class="mt-2 flex flex-wrap gap-2">
                <template x-for="v in [['all','Semua akses'],['yes','Dapat diakses'],['no','Tidak dapat diakses']]" :key="'ac-' + v[0]">
                    <button @click="access = v[0]" class="px-3 py-1.5 rounded-full text-xs font-medium border transition"
                        :class="access === v[0] ? 'bg-brand text-white border-brand' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-400'">
                        <span x-text="v[1] + (v[0] === 'all' ? '' : ' (' + countAccess(v[0]) + ')')"></span>
                    </button>
                </template>
            </div>

            {{-- Category chips --}}
            <div class="mt-2 flex flex-wrap gap-2">
                <button @click="cat = 'all'" class="px-3 py-1.5 rounded-full text-xs font-medium border transition"
                    :class="cat === 'all' ? 'bg-brand text-white border-brand' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-400'">
                    Semua kategori
                </button>
                <template x-for="c in cats" :key="'cat-' + c">
                    <button @click="cat = c" class="px-3 py-1.5 rounded-full text-xs font-medium border transition"
                        :class="cat === c ? 'bg-brand text-white border-brand' : 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-slate-400'">
                        <span x-text="label(c) + ' (' + countCat(c) + ')'"></span>
                    </button>
                </template>
            </div>

            {{-- Search --}}
            <div class="relative mt-5 max-w-xl">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                <input type="search" x-model="q" placeholder="Cari nama aplikasi, OPD, atau deskripsi…"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 pl-10 pr-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
            </div>

            {{-- Grid --}}
            <div class="mt-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <template x-for="a in filtered" :key="a.id">
                    <article class="rounded-xl border p-5 flex flex-col gap-3 transition"
                        :class="a.can_access ? 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-brand/60' : 'border-dashed border-slate-300 dark:border-slate-600 bg-slate-100/70 dark:bg-slate-800/40'">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-[11px] font-semibold tracking-[0.12em] uppercase text-slate-400" x-text="a.opd"></p>
                            <div class="flex gap-1.5">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold"
                                    :class="a.active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300'"
                                    x-text="a.active ? 'Aktif' : 'Tidak aktif'"></span>
                                <template x-if="!a.can_access">
                                    <span class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        <span class="material-symbols-outlined" style="font-size:12px">lock</span>Tidak Memiliki Akses
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center gap-3" :class="a.can_access ? '' : 'opacity-60'">
                            <div class="w-11 h-11 rounded-lg grid place-items-center text-sm font-bold text-slate-500 dark:text-slate-300"
                                :class="a.can_access ? 'bg-slate-100 dark:bg-slate-700' : 'bg-slate-200 dark:bg-slate-700/60'">
                                <span x-show="a.can_access" x-text="initials(a.name)"></span>
                                <span x-show="!a.can_access" class="material-symbols-outlined">lock</span>
                            </div>
                            <div>
                                <h3 class="font-semibold leading-tight flex items-center gap-1.5 flex-wrap">
                                    <span x-text="a.name"></span>
                                    <template x-if="a.is_new">
                                        <span class="px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300 text-[10px] font-semibold uppercase">Baru</span>
                                    </template>
                                </h3>
                                <p class="text-xs text-slate-400">
                                    <span x-text="a.description"></span> · <span class="capitalize" x-text="a.category"></span>
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <template x-for="l in a.links" :key="a.id + '-' + l.label">
                                <a x-bind:href="a.can_access ? ('/launch/' + a.slug + '/' + l.id) : null"
                                    x-bind:target="a.can_access ? '_blank' : null" rel="noopener"
                                    @click="if (! a.can_access) { $event.preventDefault(); denied(); }"
                                    :aria-disabled="a.can_access ? 'false' : 'true'"
                                    class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition select-none"
                                    :class="a.can_access ? 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:border-brand hover:text-brand cursor-pointer' : 'border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 cursor-not-allowed bg-slate-50 dark:bg-slate-800/60'"
                                    x-text="l.label"></a>
                            </template>
                        </div>

                        <p class="mt-auto pt-2 border-t text-xs text-slate-400 flex items-center gap-1.5"
                            :class="a.can_access ? 'border-slate-100 dark:border-slate-700' : 'border-slate-200/70 dark:border-slate-700/60'">
                            <span class="material-symbols-outlined" style="font-size:15px">bar_chart</span>
                            <span x-text="fmt(a.month_visits) + ' pengunjung bln ini · ' + fmt(a.year_visits) + ' thn ini'"></span>
                        </p>
                    </article>
                </template>
            </div>

            <p x-show="filtered.length === 0" class="mt-10 text-center text-sm text-slate-500">
                Tidak ada aplikasi yang cocok dengan filter.
            </p>
        </section>
    </div>

    {{-- ======= Toast (klik tombol tanpa akses / simulasi kunjungan) ======= --}}
    <div x-show="toastShow" x-transition x-cloak
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 rounded-lg bg-ink text-white text-sm px-4 py-3 shadow-lg">
        <span class="material-symbols-outlined text-red-400" style="font-size:18px">lock</span>
        <span x-text="toastMsg"></span>
    </div>
</div>
@endsection
