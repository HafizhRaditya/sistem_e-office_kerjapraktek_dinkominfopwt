@extends('layouts.admin')

@section('title', 'Ubah Kategori — '.$category->name)
@section('heading', 'Ubah Kategori')

@section('content')
<div class="max-w-3xl space-y-8">
    <a href="{{ route('admin.categories.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke daftar
    </a>

    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}">
            @csrf
            @method('PUT')
            @include('admin.kategori._form', ['category' => $category])

            <div class="mt-6">
                <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">Simpan Perubahan</button>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-semibold">Status dan Relasi</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Kategori tidak dihapus permanen agar pengelompokan aplikasi dapat dipulihkan dengan mengaktifkannya kembali.
        </p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950/50">
                <p class="text-xs text-slate-400">Status</p>
                <p class="mt-1 font-semibold">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-950/50">
                <p class="text-xs text-slate-400">Aplikasi terhubung</p>
                <p class="mt-1 font-semibold">{{ number_format($category->applications_count, 0, ',', '.') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.categories.status', $category) }}" class="mt-5"
            onsubmit="return confirm('{{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }} kategori &quot;{{ $category->name }}&quot;? Relasi aplikasi tetap disimpan.');">
            @csrf
            @method('PATCH')
            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-200">
                {{ $category->is_active ? 'Nonaktifkan Kategori' : 'Aktifkan Kategori' }}
            </button>
        </form>
    </div>
</div>
@endsection
