@extends('layouts.guest')

@section('title', 'Ubah Sandi')

@section('content')
<main class="min-h-screen grid place-items-center p-4 sm:p-6">
    <div class="w-full max-w-md">

        <a href="{{ route('dashboard') }}" class="flex items-center justify-center gap-1.5 mb-5 text-sm font-medium text-white/90 hover:text-white">
            <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke Dashboard
        </a>

        <div class="rounded-2xl bg-white shadow-2xl shadow-black/20 p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="w-1.5 h-7 rounded-full bg-brand"></span>
                <h1 class="text-xl font-bold tracking-tight text-brand uppercase">Ubah Password</h1>
            </div>
            <p class="mt-2 text-sm text-slate-500">Demi keamanan, masukkan kata sandi lama sebelum menggantinya.</p>

            @if (session('status'))
                <div class="mt-5 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-brand font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5" novalidate>
                @csrf
                @method('PUT')

                <div x-data="{ show: false }">
                    <label for="current_password" class="block text-sm font-medium mb-1.5 text-slate-700">Kata sandi lama</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">lock</span>
                        <input id="current_password" name="current_password" :type="show ? 'text' : 'password'" placeholder="••••••••"
                            autocomplete="current-password"
                            class="w-full rounded-lg border border-slate-300 bg-white pl-10 pr-11 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
                        <button type="button" @click="show = !show" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                            <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                    @error('current_password') <p class="mt-1.5 text-xs text-brand">{{ $message }}</p> @enderror
                </div>

                <div x-data="{ show: false }">
                    <label for="password" class="block text-sm font-medium mb-1.5 text-slate-700">Kata sandi baru</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">lock_reset</span>
                        <input id="password" name="password" :type="show ? 'text' : 'password'" placeholder="Min. 8 karakter, huruf & angka"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-300 bg-white pl-10 pr-11 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
                        <button type="button" @click="show = !show" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100">
                            <span class="material-symbols-outlined" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                    @error('password') <p class="mt-1.5 text-xs text-brand">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium mb-1.5 text-slate-700">Ulangi kata sandi baru</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">lock_reset</span>
                        <input id="password_confirmation" name="password_confirmation" type="password" placeholder="••••••••"
                            autocomplete="new-password"
                            class="w-full rounded-lg border border-slate-300 bg-white pl-10 pr-3 py-2.5 text-sm text-slate-900 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
                    </div>
                </div>

                <button type="submit"
                    class="w-full rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold py-3 tracking-wide transition shadow-sm">
                    SIMPAN PERUBAHAN
                </button>
            </form>
        </div>
    </div>
</main>
@endsection
