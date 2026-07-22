@extends('layouts.admin')

@section('title', 'Ubah Pengguna — '.$user->name)
@section('heading', 'Ubah Pengguna')

@section('content')
<div class="max-w-3xl space-y-8">
    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span> Kembali ke daftar
    </a>

    @if ($isSelf)
        <div class="rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
            <span class="material-symbols-outlined align-middle text-slate-400" style="font-size:18px">info</span>
            Ini akun Anda sendiri. Peran dan status aktif dikunci, dan akun ini tidak dapat dihapus — untuk mencegah Anda mengunci diri sendiri dari panel admin.
        </div>
    @endif

    {{-- Detail --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <h2 class="text-base font-semibold mb-5">Detail Pengguna</h2>
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            @include('admin.pengguna._form', ['user' => $user, 'isSelf' => $isSelf])
            <div class="mt-6">
                <button type="submit" class="rounded-lg bg-brand hover:bg-branddark text-white text-sm font-semibold px-5 py-2.5 transition">Simpan Perubahan</button>
            </div>
        </form>
    </div>

    {{-- Reset password --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <h2 class="text-base font-semibold">Reset Kata Sandi</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Menetapkan kata sandi baru untuk pengguna ini. Aktivitas ini dicatat pada log.</p>
        <form method="POST" action="{{ route('admin.users.password', $user) }}" class="mt-4 grid sm:grid-cols-2 gap-5"
            onsubmit="return confirm('Reset kata sandi &quot;{{ $user->name }}&quot;? Sandi lama langsung tidak berlaku dan pengguna harus memakai sandi baru ini.');">
            @csrf
            @method('PUT')
            <div>
                <label for="reset_password" class="block text-sm font-medium mb-1.5">Kata sandi baru</label>
                <input id="reset_password" name="password" type="password" autocomplete="new-password" placeholder="Min. 8 karakter, huruf & angka"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
                @error('password') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="reset_password_confirmation" class="block text-sm font-medium mb-1.5">Ulangi kata sandi baru</label>
                <input id="reset_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="rounded-lg border border-brand text-brand hover:bg-brand hover:text-white text-sm font-semibold px-4 py-2 transition">Reset Kata Sandi</button>
            </div>
        </form>
    </div>

    {{-- Status akun: pengganti aksi hapus (akun tidak pernah dihapus permanen) --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6">
        <h2 class="text-base font-semibold">Status Akun</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Akun tidak dapat dihapus permanen. Menonaktifkan akun menghentikan aksesnya ke portal,
            sementara hak akses, riwayat kunjungan, dan partisipasi kuisionernya tetap tersimpan
            dan pulih utuh bila akun diaktifkan kembali.
        </p>

        <div class="mt-4 flex flex-wrap items-center gap-4">
            <span class="text-sm">Status saat ini:</span>
            @if ($user->is_active)
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Aktif</span>
            @else
                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300">Tidak aktif</span>
            @endif

            @if ($isSelf)
                <span class="text-sm font-medium text-slate-400">Akun sendiri tidak dapat dinonaktifkan.</span>
            @else
                <form method="POST" action="{{ route('admin.users.status', $user) }}" class="ml-auto"
                    onsubmit="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun &quot;{{ $user->name }}&quot;?');">
                    @csrf @method('PATCH')
                    <button type="submit" class="rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-brand hover:text-brand text-sm font-semibold px-4 py-2 transition">
                        {{ $user->is_active ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}
                    </button>
                </form>
            @endif
        </div>

        @if (! $user->is_active)
            <p class="mt-4 text-xs text-slate-400">
                Akun nonaktif tidak dapat masuk ke portal meski kata sandinya benar.
            </p>
        @endif
    </div>
</div>
@endsection
