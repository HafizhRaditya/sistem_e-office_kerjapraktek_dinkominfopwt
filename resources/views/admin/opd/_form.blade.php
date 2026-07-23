@php($currentOpd = $opd ?? null)

@if ($errors->any())
    <div class="mb-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm font-medium text-brand">{{ $errors->first() }}</div>
@endif

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="code" class="mb-1.5 block text-sm font-medium">Kode OPD</label>
        <input id="code" name="code" type="text" maxlength="30" value="{{ old('code', $currentOpd?->code) }}" placeholder="Contoh: DINKOMINFO"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm uppercase focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
        <p class="mt-1 text-xs text-slate-400">Disimpan sebagai huruf kapital. Boleh memakai angka, titik, garis bawah, dan tanda hubung.</p>
        @error('code') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium">Nama OPD</label>
        <input id="name" name="name" type="text" maxlength="150" value="{{ old('name', $currentOpd?->name) }}" placeholder="Dinas Komunikasi dan Informatika"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
        @error('name') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $currentOpd?->is_active ?? true)) class="h-4 w-4 accent-brand">
            OPD aktif
        </label>
        <p class="mt-1 text-xs text-slate-400">Menonaktifkan OPD tidak menghapus pengguna, aplikasi, atau histori yang sudah terhubung.</p>
    </div>
</div>
