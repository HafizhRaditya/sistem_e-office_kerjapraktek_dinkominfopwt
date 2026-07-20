{{--
    Themed 403 page (LANGKAH 3 — cosmetic only).

    Appearance only: the guards in EnsureUserIsAdmin and LaunchController still
    decide *when* a 403 happens, and the specific abort() message is still shown
    verbatim, because it tells the admin which of the three rejections fired
    (no access / application inactive / link inactive). Falling back to a generic
    sentence only when abort() carried no message keeps that contract intact.

    Standalone by design: layouts/app.blade.php belongs to MAU's module, and the
    admin layout assumes an authenticated admin — neither holds for a 403 that a
    guest or a pegawai can hit.
--}}
@php
    $detail = trim($exception?->getMessage() ?? '');
    $user = auth()->user();

    if ($user) {
        $backUrl = $user->isAdmin() ? route('admin.home') : route('dashboard');
        $backLabel = $user->isAdmin() ? 'Kembali ke Panel Admin' : 'Kembali ke Dashboard';
    } else {
        $backUrl = route('login');
        $backLabel = 'Masuk ke E-Office';
    }
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Akses Ditolak</title>

    <script>
        (function () {
            const saved = localStorage.getItem('theme');
            const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300,0,0" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100">

<main class="min-h-screen flex items-center justify-center px-5 py-12">
    <div class="w-full max-w-lg text-center">

        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-brand/10 text-brand">
            <span class="material-symbols-outlined" style="font-size:44px" aria-hidden="true">lock</span>
        </div>

        <p class="mt-6 text-sm font-bold uppercase tracking-[0.2em] text-brand">Error 403</p>

        <h1 class="mt-2 text-2xl sm:text-3xl font-bold tracking-tight">
            Anda tidak memiliki akses ke halaman ini
        </h1>

        @if ($detail !== '' && $detail !== 'Anda tidak memiliki akses ke halaman ini')
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $detail }}</p>
        @endif

        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
            Bila Anda merasa seharusnya punya akses, hubungi administrator E-Office
            untuk meminta hak akses pada aplikasi ini.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="{{ $backUrl }}"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-5 py-2.5 transition">
                <span class="material-symbols-outlined" style="font-size:18px" aria-hidden="true">arrow_back</span>
                {{ $backLabel }}
            </a>

            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-brand hover:text-brand text-sm font-semibold px-5 py-2.5 transition">
                        Keluar
                    </button>
                </form>
            @endauth
        </div>

        <p class="mt-10 text-xs text-slate-400">E-Office Banyumas — Pemerintah Kabupaten Banyumas</p>
    </div>
</main>

</body>
</html>
