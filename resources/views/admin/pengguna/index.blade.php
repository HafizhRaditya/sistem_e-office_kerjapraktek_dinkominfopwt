@extends('layouts.admin')

@section('title', 'Manajemen Pengguna')
@section('heading', 'Manajemen Pengguna')

@section('content')
<div class="max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">Kelola akun pegawai dan administrator portal.</p>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-4 py-2.5 transition">
            <span class="material-symbols-outlined" style="font-size:18px">person_add</span> Tambah Pengguna
        </a>
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-brand font-medium">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Live search + filters + table + pagination (server-side, via Livewire) --}}
    <livewire:admin.user-table />
</div>
@endsection
