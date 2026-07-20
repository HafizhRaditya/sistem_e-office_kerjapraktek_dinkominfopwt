<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — EOB Admin</title>

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
<body class="font-sans min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100">

@php
    $admin = auth()->user();
    $adminInitials = $admin
        ? \Illuminate\Support\Str::of($admin->name)->explode(' ')->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->take(2)->implode('')
        : '';

    $navItems = [
        ['label' => 'Dashboard', 'icon' => 'grid_view', 'url' => route('dashboard'), 'active' => false, 'enabled' => true],
        ['label' => 'Manajemen Hak Akses', 'icon' => 'key', 'url' => route('admin.akses.index'), 'active' => request()->routeIs('admin.akses.*'), 'enabled' => true],
        ['label' => 'Manajemen Aplikasi', 'icon' => 'apps', 'url' => \Illuminate\Support\Facades\Route::has('admin.aplikasi.index') ? route('admin.aplikasi.index') : null, 'active' => request()->routeIs('admin.aplikasi.*'), 'enabled' => \Illuminate\Support\Facades\Route::has('admin.aplikasi.index')],
        ['label' => 'Manajemen Pengguna', 'icon' => 'group', 'url' => \Illuminate\Support\Facades\Route::has('admin.users.index') ? route('admin.users.index') : null, 'active' => request()->routeIs('admin.users.*'), 'enabled' => \Illuminate\Support\Facades\Route::has('admin.users.index')],
        ['label' => 'Statistik Kuisioner', 'icon' => 'poll', 'url' => \Illuminate\Support\Facades\Route::has('admin.questionnaires.statistics') ? route('admin.questionnaires.statistics') : null, 'active' => request()->routeIs('admin.questionnaires.*'), 'enabled' => \Illuminate\Support\Facades\Route::has('admin.questionnaires.statistics')],
        ['label' => 'Log Aktivitas', 'icon' => 'history', 'url' => \Illuminate\Support\Facades\Route::has('admin.logs.index') ? route('admin.logs.index') : null, 'active' => request()->routeIs('admin.logs.*'), 'enabled' => \Illuminate\Support\Facades\Route::has('admin.logs.index')],
    ];
@endphp

<div class="flex min-h-screen" x-data="{ open: false }">

    {{-- ======= Sidebar ======= --}}
    <aside class="fixed lg:static inset-y-0 left-0 z-40 w-64 shrink-0 bg-ink text-slate-300 flex flex-col transition-transform lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'">
        <div class="h-16 flex items-center gap-3 px-5 border-b border-slate-800">
            <div class="w-9 h-9 rounded-lg bg-brand text-white grid place-items-center font-bold">E</div>
            <div class="leading-tight">
                <p class="font-semibold text-white tracking-tight">EOB <span class="text-red-400">Admin</span></p>
                <p class="text-[10px] uppercase tracking-widest text-slate-500">E-Office Banyumas</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1 text-sm">
            @foreach ($navItems as $item)
                @if ($item['enabled'])
                    <a href="{{ $item['url'] }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $item['active'] ? 'bg-brand text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <span class="material-symbols-outlined" style="font-size:20px">{{ $item['icon'] }}</span>
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 cursor-not-allowed"
                        title="Segera hadir">
                        <span class="material-symbols-outlined" style="font-size:20px">{{ $item['icon'] }}</span>
                        {{ $item['label'] }}
                        <span class="ml-auto text-[10px] uppercase tracking-wider text-slate-700">Segera</span>
                    </span>
                @endif
            @endforeach
        </nav>

        {{-- User info at the bottom --}}
        <div class="border-t border-slate-800 p-4">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-full bg-brand text-white grid place-items-center text-xs font-bold">{{ $adminInitials }}</span>
                <div class="min-w-0 leading-tight">
                    <p class="text-sm font-medium text-white truncate">{{ optional($admin)->name }}</p>
                    <p class="text-[11px] text-slate-500 truncate">{{ optional(optional($admin)->opd)->code }} · Administrator</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center gap-2 rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 hover:bg-slate-800 transition">
                    <span class="material-symbols-outlined" style="font-size:16px">logout</span> Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- Overlay for mobile sidebar --}}
    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- ======= Main ======= --}}
    <div class="flex-1 flex flex-col min-w-0">
        <header class="sticky top-0 z-20 h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center gap-3 px-5">
            <button @click="open = true" class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <h1 class="font-semibold tracking-tight">@yield('heading', 'Panel Administrator')</h1>
            <button onclick="toggleTheme()" aria-label="Ganti tema" class="ml-auto p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 transition">
                <span class="material-symbols-outlined dark:hidden">dark_mode</span>
                <span class="material-symbols-outlined hidden dark:inline">light_mode</span>
            </button>
        </header>

        <main class="flex-1 p-5 sm:p-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
@livewireScriptConfig
</body>
</html>
