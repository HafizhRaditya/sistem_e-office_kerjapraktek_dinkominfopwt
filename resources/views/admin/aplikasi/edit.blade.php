@extends('layouts.admin')

@section('title', 'Ubah Aplikasi — '.$application->name)
@section('heading', 'Ubah Aplikasi')

@section('content')
<div class="max-w-3xl space-y-8">
    <a href="{{ route('admin.aplikasi.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke daftar
    </a>

    {{-- Application detail --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <h2 class="text-base font-semibold mb-5">Detail Aplikasi</h2>
        <form method="POST" action="{{ route('admin.aplikasi.update', $application) }}">
            @csrf
            @method('PUT')
            @include('admin.aplikasi._form', ['application' => $application])
            <div class="mt-6">
                <button type="submit" class="rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-5 py-2.5 transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>

    {{-- Links --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-base font-semibold">Tautan Aplikasi <span class="text-slate-400 font-normal">({{ $application->links->count() }})</span></h2>
            <a href="{{ route('admin.aplikasi.link.create', $application) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:border-brand hover:text-brand transition">
                <span class="material-symbols-outlined" style="font-size:16px">add_link</span> Tambah Tautan
            </a>
        </div>

        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($application->links as $link)
                <div class="flex items-center gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ $link->label }}
                            @if (! $link->is_active)<span class="ml-1 text-xs text-red-500">(nonaktif)</span>@endif
                        </p>
                        <p class="text-xs text-slate-400 truncate">{{ $link->url }}</p>
                    </div>
                    <span class="text-xs text-slate-400">urut {{ $link->sort_order }}</span>
                    <a href="{{ route('admin.aplikasi.link.edit', [$application, $link]) }}" class="p-1.5 rounded-md text-slate-500 hover:text-brand hover:bg-slate-100 dark:hover:bg-slate-800" title="Ubah">
                        <span class="material-symbols-outlined" style="font-size:18px">edit</span>
                    </a>
                    <form method="POST" action="{{ route('admin.aplikasi.link.destroy', [$application, $link]) }}" onsubmit="return confirm('Hapus tautan &quot;{{ $link->label }}&quot;?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-1.5 rounded-md text-slate-500 hover:text-brand hover:bg-slate-100 dark:hover:bg-slate-800" title="Hapus">
                            <span class="material-symbols-outlined" style="font-size:18px">delete</span>
                        </button>
                    </form>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-500">Belum ada tautan. Tambahkan minimal satu tombol peluncuran.</p>
            @endforelse
        </div>
    </div>

    {{-- Danger zone --}}
    <div class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/50 dark:bg-red-900/10 p-6">
        <h2 class="text-base font-semibold text-brand">Hapus Aplikasi</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Menghapus aplikasi ini juga menghapus seluruh tautan, hak akses pegawai, dan riwayat kunjungannya. Tindakan ini tidak dapat dibatalkan.</p>
        <form method="POST" action="{{ route('admin.aplikasi.destroy', $application) }}" class="mt-4"
            onsubmit="return confirm('Hapus aplikasi &quot;{{ $application->name }}&quot; beserta semua tautan, hak akses, dan kunjungannya? Tindakan ini permanen.');">
            @csrf @method('DELETE')
            <button type="submit" class="rounded-lg border border-brand text-brand hover:bg-brand hover:text-white text-sm font-semibold px-4 py-2 transition">Hapus Aplikasi</button>
        </form>
    </div>
</div>
@endsection
