@extends('layouts.admin')

@section('title', 'Manajemen Aplikasi')
@section('heading', 'Manajemen Aplikasi')

@section('content')
<div class="max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola aplikasi portal beserta tautan peluncurannya.</p>
        <a href="{{ route('admin.aplikasi.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-4 py-2.5 transition">
            <span class="material-symbols-outlined" style="font-size:18px">add</span> Tambah Aplikasi
        </a>
    </div>

    {{-- Live search + table + pagination (server-side, via Livewire) --}}
    <livewire:admin.application-table />
</div>
@endsection
