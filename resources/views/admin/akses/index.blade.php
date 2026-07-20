@extends('layouts.admin')

@section('title', 'Manajemen Hak Akses')
@section('heading', 'Manajemen Hak Akses')

@section('content')
<div class="max-w-6xl">
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Atur aplikasi yang dapat diakses tiap pegawai. Admin memiliki akses ke semua aplikasi (bypass).
    </p>

    {{-- Live search + OPD filter + table + pagination (server-side, via Livewire) --}}
    <livewire:admin.access-table />
</div>
@endsection
