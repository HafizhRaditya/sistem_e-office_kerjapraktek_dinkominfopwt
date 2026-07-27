@php($currentCategory = $category ?? null)

@if ($errors->any())
    <div class="mb-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm font-medium text-brand">{{ $errors->first() }}</div>
@endif

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium">Nama kategori</label>
        <input id="name" name="name" type="text" maxlength="100" value="{{ old('name', $currentCategory?->name) }}" placeholder="Contoh: Pelayanan Publik"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
        @error('name') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="slug" class="mb-1.5 block text-sm font-medium">Slug <span class="font-normal text-slate-400">(opsional)</span></label>
        <input id="slug" name="slug" type="text" maxlength="100" value="{{ old('slug', $currentCategory?->slug) }}" placeholder="pelayanan-publik"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm lowercase focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
        <p class="mt-1 text-xs text-slate-400">Jika dikosongkan, slug dibuat otomatis dari nama kategori.</p>
        @error('slug') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="sort_order" class="mb-1.5 block text-sm font-medium">Urutan</label>
        <input id="sort_order" name="sort_order" type="number" min="0" max="999999" value="{{ old('sort_order', $currentCategory?->sort_order ?? 0) }}"
            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
        <p class="mt-1 text-xs text-slate-400">Angka lebih kecil ditampilkan lebih dahulu.</p>
        @error('sort_order') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-end pb-3">
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $currentCategory?->is_active ?? true)) class="h-4 w-4 accent-brand">
            Kategori aktif
        </label>
    </div>

    <div class="sm:col-span-2 rounded-lg bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-950/50 dark:text-slate-400">
        Menonaktifkan kategori hanya menyembunyikan kategori tersebut dari dashboard. Aplikasi dan relasi yang sudah terhubung tidak dihapus.
    </div>
</div>
