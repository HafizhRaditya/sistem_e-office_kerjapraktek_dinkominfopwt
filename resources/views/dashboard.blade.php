@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div x-data="dashboard(@js($apps), @js($heroSlides ?? []), @js($popupSlides ?? []))">

    {{-- ======= Hero (red brand surface) ======= --}}
    <section class="relative overflow-hidden bg-slate-900 text-white"
        @mouseenter="heroPaused = true" @mouseleave="heroPaused = false"
        @touchstart.passive="touchStart('hero', $event)" @touchend.passive="touchEnd('hero', $event)">
        <template x-for="(slide, index) in heroSlides" :key="'hero-' + slide.id">
            <div x-show="heroIndex === index" x-transition.opacity class="absolute inset-0">
                <template x-if="slide.image">
                    <img :src="slide.image" :alt="slide.title" class="h-full w-full object-cover">
                </template>
                <div class="absolute inset-0 bg-gradient-to-b from-slate-950/45 via-slate-950/30 to-slate-950/55"></div>
            </div>
        </template>
        <div class="relative max-w-7xl mx-auto px-6 pt-14 pb-16 text-center">
            <p class="text-[11px] font-semibold tracking-[0.2em] uppercase text-red-200">Sistem Pemerintahan Berbasis Elektronik</p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight">E-Office Kabupaten Banyumas</h1>
            <p class="mt-4 max-w-2xl mx-auto text-red-50/90 text-sm sm:text-base leading-relaxed">
                Portal aplikasi terintegrasi bagi user OPD — seluruh aplikasi ditampilkan;
                aplikasi di luar hak akses Anda ditandai dan tidak dapat dibuka.
            </p>
            <template x-if="heroSlides.length > 0">
                <div class="mt-4 min-h-[3.5rem] max-w-2xl mx-auto">
                    <p class="text-lg sm:text-xl font-semibold" x-text="heroSlides[heroIndex].title"></p>
                    <p class="mt-1 text-red-50/90 text-sm sm:text-base leading-relaxed"
                        x-text="heroSlides[heroIndex].description || 'Informasi terbaru dari Portal E-Office Kabupaten Banyumas.'"></p>
                </div>
            </template>
            <a href="#apps" class="mt-7 inline-flex items-center gap-2 rounded-lg bg-white text-brand hover:bg-red-50 px-6 py-3 text-sm font-semibold transition shadow-sm">
                Mulai eksplorasi aplikasi <span class="material-symbols-outlined" style="font-size:18px">arrow_forward</span>
            </a>
        </div>
        <template x-if="heroSlides.length > 1">
            <div class="absolute bottom-4 left-1/2 flex -translate-x-1/2 items-center gap-1.5">
                <template x-for="(slide, index) in heroSlides" :key="'hero-dot-' + slide.id">
                    <button type="button" @click="heroIndex = index" :aria-label="'Tampilkan banner ' + (index + 1)"
                        class="h-2 rounded-full transition-all" :class="heroIndex === index ? 'w-6 bg-white' : 'w-2 bg-white/50 hover:bg-white/80'"></button>
                </template>
            </div>
        </template>
    </section>

    {{-- ======= Popup banner dan kuisioner (pegawai aktif) ======= --}}
    <div x-show="popupOpen && popupSlides.length > 0" x-cloak x-transition.opacity
        class="fixed inset-0 z-50 grid place-items-center bg-slate-950/65 p-4 sm:p-6"
        role="dialog" aria-modal="true" aria-label="Informasi dan kuisioner"
        @keydown.escape.window="closePopup()">
        <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800"
            @click.outside="closePopup()"
            @focusin="popupPaused = true" @focusout="popupPaused = false"
            @touchstart.passive="touchStart('popup', $event)" @touchend.passive="touchEnd('popup', $event)">
            <button type="button" @click="closePopup()" aria-label="Tutup popup"
                class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-slate-950/45 text-white transition hover:bg-slate-950/70 focus:outline-none focus:ring-2 focus:ring-white">
                <span class="material-symbols-outlined" style="font-size:20px">close</span>
            </button>

            <template x-if="currentPopupSlide">
                <article>
                    <div class="relative aspect-[16/7] bg-gradient-to-br from-branddark via-brand to-branddark">
                        <template x-if="currentPopupSlide.image">
                            <img :src="currentPopupSlide.image" :alt="currentPopupSlide.title" class="h-full w-full object-cover">
                        </template>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/25 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-5 text-white sm:p-7">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.2em] text-red-200"
                                x-text="currentPopupSlide.type === 'questionnaire' ? 'Kuisioner' : 'Informasi'"></span>
                            <h2 class="mt-1 text-xl font-bold sm:text-2xl" x-text="currentPopupSlide.title"></h2>
                        </div>
                    </div>

                    <div class="flex min-h-[14rem] flex-col p-5 sm:p-7">
                        <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                            x-text="currentPopupSlide.description || (currentPopupSlide.type === 'questionnaire' ? 'Bantu kami meningkatkan layanan E-Office dengan mengisi kuisioner ini.' : '')"></p>

                        <div class="mt-auto flex flex-wrap items-center justify-between gap-3 pt-5">
                            <div class="flex items-center gap-1.5" aria-label="Posisi slide">
                                <template x-for="(item, dotIndex) in popupSlides" :key="'popup-dot-' + item.type + '-' + item.id">
                                    <button type="button" @click="selectSlide(dotIndex)" :aria-label="'Tampilkan slide ' + (dotIndex + 1)"
                                        class="h-2 rounded-full transition-all" :class="popupIndex === dotIndex ? 'w-6 bg-brand' : 'w-2 bg-slate-300 hover:bg-slate-400 dark:bg-slate-600 dark:hover:bg-slate-500'"></button>
                                </template>
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" x-show="popupSlides.length > 1" @click="moveSlide('popup', -1)"
                                    class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-brand hover:text-brand dark:border-slate-600 dark:text-slate-300"
                                    aria-label="Slide sebelumnya">
                                    <span class="material-symbols-outlined" style="font-size:18px">chevron_left</span>
                                </button>
                                <button type="button" x-show="popupSlides.length > 1" @click="moveSlide('popup', 1)"
                                    class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-600 transition hover:border-brand hover:text-brand dark:border-slate-600 dark:text-slate-300"
                                    aria-label="Slide berikutnya">
                                    <span class="material-symbols-outlined" style="font-size:18px">chevron_right</span>
                                </button>

                                <template x-if="currentPopupSlide.type === 'questionnaire'">
                                    <form method="POST" :action="currentPopupSlide.click_url">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark focus:outline-none focus:ring-2 focus:ring-brand/30">
                                            Isi Kuisioner
                                            <span class="material-symbols-outlined" style="font-size:18px">open_in_new</span>
                                        </button>
                                    </form>
                                </template>
                                <template x-if="currentPopupSlide.type === 'banner' && currentPopupSlide.target_url">
                                    <a :href="currentPopupSlide.target_url" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark focus:outline-none focus:ring-2 focus:ring-brand/30">
                                        Buka informasi
                                        <span class="material-symbols-outlined" style="font-size:18px">open_in_new</span>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 pb-20">

        @php
            $profileInitials = \Illuminate\Support\Str::of($user->name)
                ->explode(' ')
                ->filter()
                ->map(fn ($word) => \Illuminate\Support\Str::substr($word, 0, 1))
                ->take(2)
                ->implode('');

            $statCards = [
                [
                    'label' => 'Dapat diakses',
                    'value' => $userStats['accessible_apps'],
                    'description' => 'Aplikasi tersedia untuk akun Anda',
                    'icon' => 'apps',
                    'icon_class' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                ],
                [
                    'label' => 'Akses terbatas',
                    'value' => $userStats['restricted_apps'],
                    'description' => 'Aplikasi yang belum dapat dibuka',
                    'icon' => 'lock',
                    'icon_class' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                ],
                [
                    'label' => 'Kunjungan bulan ini',
                    'value' => $userStats['month_visits'],
                    'description' => 'Peluncuran aplikasi yang valid',
                    'icon' => 'calendar_month',
                    'icon_class' => 'bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
                ],
                [
                    'label' => 'Kunjungan tahun ini',
                    'value' => $userStats['year_visits'],
                    'description' => 'Total aktivitas sepanjang tahun',
                    'icon' => 'monitoring',
                    'icon_class' => 'bg-red-50 text-brand dark:bg-red-950/50 dark:text-red-300',
                ],
            ];
        @endphp

        {{-- ======= Profil dan statistik pengguna ======= --}}
        <section class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.6fr)]" aria-label="Profil dan statistik pengguna">
            <article class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="h-1.5 bg-brand"></div>
                <div class="p-5 sm:p-6">
                    <div class="flex items-start gap-4">
                        <div class="grid h-14 w-14 flex-none place-items-center rounded-full bg-red-50 text-lg font-bold text-brand dark:bg-red-950/50 dark:text-red-200">
                            {{ $profileInitials }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xs font-semibold uppercase text-brand">Profil pengguna</p>
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $user->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    {{ $user->is_active ? 'Akun aktif' : 'Akun nonaktif' }}
                                </span>
                            </div>
                            <h2 class="mt-1 break-words text-lg font-semibold leading-snug">{{ $user->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ $user->isAdmin() ? 'Administrator' : 'Pegawai' }}
                                @if ($user->opd)
                                    <span aria-hidden="true">&middot;</span> {{ $user->opd->code }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <dl class="mt-5 divide-y divide-slate-100 border-t border-slate-100 text-sm dark:divide-slate-700 dark:border-slate-700">
                        <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-3">
                            <dt class="text-slate-500 dark:text-slate-400">NIP/NIK</dt>
                            <dd class="break-all text-right font-medium">{{ $user->nip_nik }}</dd>
                        </div>
                        <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-3">
                            <dt class="text-slate-500 dark:text-slate-400">OPD</dt>
                            <dd class="break-words text-right font-medium">{{ optional($user->opd)->name ?? 'Belum ditentukan' }}</dd>
                        </div>
                        <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-3">
                            <dt class="text-slate-500 dark:text-slate-400">Email</dt>
                            <dd class="break-all text-right font-medium">{{ $user->email ?? 'Belum tersedia' }}</dd>
                        </div>
                        <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 pt-3">
                            <dt class="text-slate-500 dark:text-slate-400">Login terakhir</dt>
                            <dd class="text-right font-medium">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Belum tercatat' }}</dd>
                        </div>
                    </dl>
                </div>
            </article>

            <livewire:dashboard.user-statistics
                :initial-stats="$userStats"
            />


        </section>

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
                        <p class="mt-1 text-[11px] text-slate-400" x-text="fmt(a.day_visits) + ' kunjungan hari ini | ' +  fmt(a.month_visits) + ' kunjungan bulan ini | ' + fmt(a.year_visits) + ' kunjungan tahun ini' "></p>
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
                            <div class="relative w-11 h-11 shrink-0 overflow-hidden rounded-lg grid place-items-center
                                        bg-red-50 text-lg font-bold text-brand dark:bg-red-950/50 dark:text-red-200">

                                <template x-if="a.icon">
                                    <img
                                        :src="a.icon"
                                        :alt="'Logo ' + a.name"
                                        class="h-full w-full object-contain p-1"
                                        :class="a.can_access ? '' : 'grayscale opacity-40'"
                                    >
                                </template>

                                <template x-if="!a.icon">
                                    <span x-text="initials(a.name)"></span>
                                </template>

                                <template x-if="!a.can_access">
                                    <span class="absolute inset-0 grid place-items-center bg-slate-900/25">
                                        <span class="material-symbols-outlined text-white">lock</span>
                                    </span>
                                </template>
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
