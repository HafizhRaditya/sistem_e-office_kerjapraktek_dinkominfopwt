<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — E-Office Banyumas</title>

    {{-- Apply saved theme before paint to avoid a flash of the wrong mode --}}
    <script>
        (function () {
            const saved = localStorage.getItem('theme');
            const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
        function toggleTheme() {
            const dark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', dark ? 'dark' : 'light');
        }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,300,0,0" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="font-sans min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-900 dark:text-slate-100">

@php
    $navUser = auth()->user();
    $navInitials = $navUser
        ? \Illuminate\Support\Str::of($navUser->name)->explode(' ')->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->take(2)->implode('')
        : '';
@endphp

{{-- ======= Navbar (white surface, red brand) ======= --}}
<header class="sticky top-0 z-30 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
    <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between gap-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-brand text-white grid place-items-center font-bold">E</div>
            <p class="font-semibold tracking-tight">E-Office <span class="text-brand">Banyumas</span></p>
        </a>

        <div class="flex items-center gap-2">
            <button onclick="toggleTheme()" aria-label="Ganti tema"
                class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 transition">
                <span class="material-symbols-outlined dark:hidden">dark_mode</span>
                <span class="material-symbols-outlined hidden dark:inline">light_mode</span>
            </button>

            @auth
            <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                <button @click="open = !open"
                    class="flex items-center gap-2 rounded-lg bg-brand hover:bg-branddark text-white pl-2 pr-3 py-1.5 text-sm font-medium transition">
                    <span class="w-7 h-7 rounded-full bg-white/20 grid place-items-center text-xs font-bold">{{ $navInitials }}</span>
                    <span class="text-left leading-tight max-w-[10rem] truncate">
                        {{ $navUser->name }}
                        <span class="block text-[10px] font-normal text-red-100">{{ optional($navUser->opd)->code }}{{ $navUser->role === 'admin' ? ' · ADMIN' : '' }}</span>
                    </span>
                    <span class="material-symbols-outlined" style="font-size:16px">expand_more</span>
                </button>

                <div x-show="open" x-transition x-cloak @click.outside="open = false"
                    class="absolute right-0 mt-2 w-44 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 shadow-lg shadow-slate-900/10 py-1 text-sm">
                    <a href="{{ route('password.edit') }}" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50 dark:hover:bg-slate-700">
                        <span class="material-symbols-outlined" style="font-size:17px">lock</span> Ubah sandi
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-brand hover:bg-slate-50 dark:hover:bg-slate-700">
                            <span class="material-symbols-outlined" style="font-size:17px">logout</span> Logout
                        </button>
                    </form>
                </div>
            </div>
            @endauth
        </div>
    </div>
</header>

<main>
    @yield('content')
</main>

{{-- ======= Footer ======= --}}
<footer class="border-t border-slate-200 dark:border-slate-800 py-8">
    <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <p>© {{ date('Y') }} Dinkominfo Kabupaten Banyumas. Seluruh hak cipta dilindungi.</p>
        <div
            class="flex flex-wrap justify-center gap-x-5 gap-y-2"
            aria-label="Tautan resmi"
        >
            <a
                href="https://jdih.banyumaskab.go.id/assets/z-jdih/produk_hukum/view/id/553/t/peraturan%2Bmenteri%2Bkomunikasi%2Bdan%2Binformatika%2Bnomor%2B20%2Btahun%2B2016%2Btanggal%2B1%2Bdesember%2B2016.html"
                target="_blank"
                rel="noopener noreferrer"
                class="hover:text-brand"
            >
                Perlindungan data pribadi
            </a>

            <a
                href="https://dinkominfo.banyumaskab.go.id/page/24768/alamat-dan-kontak"
                target="_blank"
                rel="noopener noreferrer"
                class="hover:text-brand"
            >
                Kontak Dinkominfo
            </a>

            <a
                href="https://www.banyumaskab.go.id/"
                target="_blank"
                rel="noopener noreferrer"
                class="hover:text-brand"
            >
                Portal Banyumas
            </a>
        </div>

    </div>
</footer>

@stack('scripts')
@livewireScriptConfig
</body>
</html>
