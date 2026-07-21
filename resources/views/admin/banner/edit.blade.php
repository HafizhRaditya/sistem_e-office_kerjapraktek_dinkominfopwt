@extends('layouts.admin')

@section('title', 'Ubah Banner — '.$banner->title)
@section('heading', 'Ubah Banner')

@section('content')
<div class="w-full space-y-6">
    <a href="{{ route('admin.banners.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke daftar
    </a>

    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <div class="mb-5 border-b border-slate-100 dark:border-slate-800 pb-4">
            <h2 class="font-semibold">{{ $banner->title }}</h2>
            <p class="mt-1 text-xs text-slate-500">Dibuat oleh {{ $banner->creator?->name ?? '—' }} pada {{ $banner->created_at?->format('d M Y H:i') }}</p>
        </div>

        <form method="POST" action="{{ route('admin.banners.update', $banner) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.banner._form', ['banner' => $banner])

            <div class="mt-6">
                <button type="submit" class="rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-5 py-2.5 transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-900/10 p-6">
        <h2 class="text-base font-semibold text-brand">Hapus Banner</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Banner akan langsung hilang dari dashboard. Berkas unggahan yang dikelola sistem juga akan dihapus.</p>
        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" class="mt-4" onsubmit="return confirm('Hapus banner &quot;{{ $banner->title }}&quot;? Tindakan ini permanen.');">
            @csrf @method('DELETE')
            <button type="submit" class="rounded-lg border border-brand text-brand hover:bg-brand hover:text-white text-sm font-semibold px-4 py-2 transition">Hapus Banner</button>
        </form>
    </div>
</div>
@endsection
