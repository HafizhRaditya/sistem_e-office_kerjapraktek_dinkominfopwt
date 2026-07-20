@extends('layouts.admin')

@section('title', 'Tambah Tautan — '.$application->name)
@section('heading', 'Tambah Tautan')

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('admin.aplikasi.edit', $application) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke {{ $application->name }}
    </a>

    <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <h2 class="text-base font-semibold mb-5">Tautan baru untuk <span class="text-brand">{{ $application->name }}</span></h2>
        <form method="POST" action="{{ route('admin.aplikasi.link.store', $application) }}">
            @csrf
            @include('admin.aplikasi.link._form', ['link' => null])
            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-5 py-2.5 transition">Simpan</button>
                <a href="{{ route('admin.aplikasi.edit', $application) }}" class="text-sm font-medium text-slate-500 hover:text-brand">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
