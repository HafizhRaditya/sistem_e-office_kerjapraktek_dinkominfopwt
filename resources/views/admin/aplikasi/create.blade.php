@extends('layouts.admin')

@section('title', 'Tambah Aplikasi')
@section('heading', 'Tambah Aplikasi')

@section('content')
<div class="max-w-3xl">
    <a href="{{ route('admin.aplikasi.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke daftar
    </a>

    <div class="mt-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <form method="POST" action="{{ route('admin.aplikasi.store') }}">
            @csrf
            @include('admin.aplikasi._form', ['application' => null])

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-5 py-2.5 transition">Simpan</button>
                <a href="{{ route('admin.aplikasi.index') }}" class="text-sm font-medium text-slate-500 hover:text-brand">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
