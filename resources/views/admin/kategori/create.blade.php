@extends('layouts.admin')

@section('title', 'Tambah Kategori')
@section('heading', 'Tambah Kategori')

@section('content')
<div class="max-w-3xl">
    <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke daftar
    </a>

    <div class="mt-4 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <form method="POST" action="{{ route('admin.categories.store') }}">
            @csrf
            @include('admin.kategori._form', ['category' => null])

            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">Simpan</button>
                <a href="{{ route('admin.categories.index') }}" class="text-sm font-medium text-slate-500 hover:text-brand">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
