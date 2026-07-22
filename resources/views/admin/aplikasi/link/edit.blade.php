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

    {{-- Status ketersediaan: pengganti aksi hapus (tautan tidak pernah dihapus permanen) --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <h2 class="text-base font-semibold">Status Ketersediaan</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Tautan tidak dapat dihapus permanen. Hapus centang <span class="font-medium">Aktif</span>
            pada formulir di atas lalu simpan untuk memensiunkannya — riwayat kunjungan yang menunjuk
            tautan ini tetap utuh beserta identitas tombolnya.
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <span class="text-sm">Status saat ini:</span>
            @if ($link->is_active)
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Aktif</span>
            @else
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300">Tidak aktif</span>
            @endif
        </div>

        @if (! $link->is_active)
            <p class="mt-4 text-xs text-slate-400">
                Tautan tidak aktif ditolak saat diluncurkan — termasuk oleh admin.
            </p>
        @endif
    </div>
</div>
@endsection
