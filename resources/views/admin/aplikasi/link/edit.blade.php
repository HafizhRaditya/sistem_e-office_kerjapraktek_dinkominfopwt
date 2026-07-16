@extends('layouts.admin')

@section('title', 'Ubah Tautan — '.$application->name)
@section('heading', 'Ubah Tautan')

@section('content')
<div class="max-w-2xl space-y-6">
    <a href="{{ route('admin.aplikasi.edit', $application) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke {{ $application->name }}
    </a>

    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <h2 class="text-base font-semibold mb-5">Tautan <span class="text-brand">{{ $link->label }}</span> pada {{ $application->name }}</h2>
        <form method="POST" action="{{ route('admin.aplikasi.link.update', [$application, $link]) }}">
            @csrf
            @method('PUT')
            @include('admin.aplikasi.link._form', ['link' => $link])
            <div class="mt-6 flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-5 py-2.5 transition">Simpan Perubahan</button>
                <a href="{{ route('admin.aplikasi.edit', $application) }}" class="text-sm font-medium text-slate-500 hover:text-brand">Batal</a>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-900/10 p-6">
        <h2 class="text-base font-semibold text-brand">Hapus Tautan</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tautan ini akan dihapus dari aplikasi. Riwayat kunjungan yang menunjuk tautan ini tetap tersimpan (kolomnya dikosongkan).</p>
        <form method="POST" action="{{ route('admin.aplikasi.link.destroy', [$application, $link]) }}" class="mt-4"
            onsubmit="return confirm('Hapus tautan &quot;{{ $link->label }}&quot;?');">
            @csrf @method('DELETE')
            <button type="submit" class="rounded-lg border border-brand text-brand hover:bg-brand hover:text-white text-sm font-semibold px-4 py-2 transition">Hapus Tautan</button>
        </form>
    </div>
</div>
@endsection
