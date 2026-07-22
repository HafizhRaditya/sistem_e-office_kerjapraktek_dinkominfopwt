@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<main class="min-h-screen grid place-items-center p-4 sm:p-6">
    <div class="w-full max-w-4xl rounded-2xl bg-white shadow-2xl shadow-black/20 overflow-hidden grid lg:grid-cols-2">

        {{-- ======= Branding panel ======= --}}
        <section class="hidden lg:flex flex-col justify-between p-12 bg-white">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-brand grid place-items-center font-bold text-lg text-white">E</div>
                <div class="leading-tight">
                    <p class="font-semibold tracking-tight text-slate-900">E-Office <span class="text-brand">Banyumas</span></p>
                    <p class="text-xs text-slate-400">Dinkominfo Kabupaten Banyumas</p>
                </div>
            </div>

            <div class="max-w-md">
                <h1 class="text-4xl font-bold tracking-tight leading-tight text-slate-900">
                    Cukup sekali login untuk
                    <span class="bg-gradient-to-r from-brand to-amber-500 bg-clip-text text-transparent">semua aplikasi.</span>
                </h1>
                <p class="mt-5 text-slate-500 leading-relaxed">
                    Portal E-Office Kabupaten Banyumas mempermudah akses satu pintu untuk
                    seluruh layanan administrasi pemerintahan — cepat, efisien, dan transparan.
                </p>
            </div>

            <p class="text-xs text-slate-400 uppercase tracking-widest">
                Sistem Pemerintahan Berbasis Elektronik (SPBE) · Kabupaten Banyumas
            </p>
        </section>

        {{-- ======= Form panel ======= --}}
        <section class="flex flex-col justify-center p-6 sm:p-12">
            <div class="lg:hidden flex items-center gap-3 mb-8">
                <div class="w-10 h-10 rounded-lg bg-brand grid place-items-center font-bold text-white">E</div>
                <p class="font-semibold text-slate-900">E-Office <span class="text-brand">Banyumas</span></p>
            </div>

            <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Sign in</h2>
            <p class="mt-1 text-sm text-slate-500">Silakan masuk untuk melanjutkan tugas Anda.</p>

            @if ($errors->any())
                <div class="mt-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-brand font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-5" x-data="{ show: false }" novalidate>
                @csrf

                <div>
                    <label for="nip_nik" class="block text-sm font-medium mb-1.5 text-slate-700">NIP/NIK</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">person</span>
                        <input id="nip_nik" name="nip_nik" type="text" value="{{ old('nip_nik') }}" placeholder="NIP atau NIK"
                            autocomplete="username" autofocus
                            class="w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
                    </div>
                    @error('nip_nik') <p class="mt-1.5 text-xs text-brand">{{ $message }}</p> @enderror
                </div>

                <div>
                    {{-- Self-service password recovery is deferred (field decision,
                         Dinkominfo): resets go through an admin for now. This is plain
                         text, not a link, so nobody clicks a dead end. --}}
                    <div class="flex justify-between items-baseline gap-3 mb-1.5">
                        <label for="password" class="block text-sm font-medium text-slate-700">Kata sandi</label>
                        <span class="text-xs text-slate-500">Lupa kata sandi? Hubungi admin OPD Anda.</span>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">lock</span>
                        <input id="password" name="password" :type="show ? 'text' : 'password'" placeholder="••••••••"
                            autocomplete="current-password"
                            class="w-full rounded-lg border border-slate-300 bg-white pl-10 pr-11 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
                        <button type="button" @click="show = !show" :aria-label="show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                            class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                            <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                    @error('password') <p class="mt-1.5 text-xs text-brand">{{ $message }}</p> @enderror
                </div>

                {{-- Cloudflare Turnstile (FR-A02): real widget when a sitekey is set,
                     otherwise a dev placeholder. Server-side check is in AuthController. --}}
                @if (config('services.turnstile.sitekey'))
                    <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.sitekey') }}" data-theme="light"></div>
                @else
                    <div class="flex items-center justify-between rounded-lg border border-slate-300 bg-slate-100/70 px-4 py-3">
                        <label class="flex items-center gap-3 text-sm text-slate-600 cursor-pointer">
                            <input type="checkbox" name="turnstile" class="w-4 h-4 accent-brand"> Verify you are human
                        </label>
                        <span class="text-[10px] font-semibold tracking-widest text-slate-400 uppercase">Cloudflare</span>
                    </div>
                @endif
                @error('turnstile') <p class="mt-1.5 text-xs text-brand">{{ $message }}</p> @enderror

                <button type="submit"
                    class="w-full rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold py-3 tracking-wide transition shadow-sm">
                    MASUK SEKARANG
                </button>
            </form>

            <p class="mt-10 text-center text-xs text-slate-400">
                © {{ date('Y') }} Dinkominfo Kabupaten Banyumas. Seluruh hak cipta dilindungi.
            </p>
        </section>
    </div>
</main>
@endsection
