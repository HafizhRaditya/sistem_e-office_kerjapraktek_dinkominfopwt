@extends('layouts.admin')

@section('title', 'Ubah Kuisioner — '.$questionnaire->title)
@section('heading', 'Ubah Kuisioner')

@section('content')
<div class="w-full space-y-6">
    <a href="{{ route('admin.questionnaires.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke daftar
    </a>

    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4 dark:border-slate-800">
            <div>
                <h2 class="font-semibold">{{ $questionnaire->title }}</h2>
                <p class="mt-1 text-xs text-slate-500">Dibuat oleh {{ $questionnaire->creator?->name ?? '—' }} pada {{ $questionnaire->created_at?->format('d M Y H:i') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.questionnaires.statistics', ['questionnaire' => $questionnaire->id]) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-300">
                    <span class="material-symbols-outlined" style="font-size:17px">monitoring</span>
                    {{ number_format($questionnaire->responses_count, 0, ',', '.') }} respons
                </a>
                <a href="{{ $questionnaire->target_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-brand hover:text-brand dark:border-slate-700 dark:text-slate-300">
                    <span class="material-symbols-outlined" style="font-size:17px">open_in_new</span> Buka formulir
                </a>
            </div>
        </div>

        @if ($questionnaire->responses_count > 0)
            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-200">
                Kuisioner ini sudah memiliki {{ number_format($questionnaire->responses_count, 0, ',', '.') }} respons. Mengganti tautan formulir dapat membuat statistik menggabungkan respons dari formulir yang berbeda.
            </div>
        @endif

        <form method="POST" action="{{ route('admin.questionnaires.update', $questionnaire) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.kuisioner._form', ['questionnaire' => $questionnaire])

            <div class="mt-6">
                <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-branddark">Simpan Perubahan</button>
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-red-200 bg-red-50/50 p-6 dark:border-red-900/50 dark:bg-red-900/10">
        <h2 class="text-base font-semibold text-brand">Hapus Kuisioner</h2>
        @if ($questionnaire->responses_count > 0)
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kuisioner tidak dapat dihapus karena sudah memiliki respons. Nonaktifkan kuisioner agar tidak lagi ditampilkan tanpa menghilangkan statistik.</p>
            <button type="button" disabled class="mt-4 cursor-not-allowed rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-400 dark:border-slate-700">Hapus Kuisioner</button>
        @else
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kuisioner akan langsung hilang dari dashboard. Berkas unggahan yang dikelola sistem juga akan dihapus.</p>
            <form method="POST" action="{{ route('admin.questionnaires.destroy', $questionnaire) }}" class="mt-4" onsubmit="return confirm('Hapus kuisioner &quot;{{ $questionnaire->title }}&quot;? Tindakan ini permanen.');">
                @csrf @method('DELETE')
                <button type="submit" class="rounded-lg border border-brand px-4 py-2 text-sm font-semibold text-brand transition hover:bg-brand hover:text-white">Hapus Kuisioner</button>
            </form>
        @endif
    </div>
</div>
@endsection
