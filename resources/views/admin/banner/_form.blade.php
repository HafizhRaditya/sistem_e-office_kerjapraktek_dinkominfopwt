@php $item = $banner ?? null; @endphp

@if ($errors->any())
    <div class="mb-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-brand font-medium">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="title" class="block text-sm font-medium mb-1.5">Judul banner</label>
        <input id="title" name="title" type="text" maxlength="200" required value="{{ old('title', $item?->title) }}"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        @error('title') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="block text-sm font-medium mb-1.5">Deskripsi <span class="font-normal text-slate-400">(opsional)</span></label>
        <textarea id="description" name="description" rows="3" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">{{ old('description', $item?->description) }}</textarea>
        @error('description') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
        <h3 class="text-sm font-semibold">Gambar banner</h3>
        <p class="mt-1 text-xs text-slate-500">Unggah gambar baru atau isi URL/path aset. Unggahan baru akan menjadi pilihan utama.</p>

        @if ($item?->image_path)
            <div class="mt-4 flex flex-wrap items-center gap-4">
                <img src="{{ preg_match('/^https?:\/\//i', $item->image_path) ? $item->image_path : asset(ltrim($item->image_path, '/')) }}" alt="Pratinjau banner" class="h-24 w-40 rounded-lg border border-slate-200 dark:border-slate-700 object-cover">
                <label class="flex items-center gap-2 text-sm text-brand cursor-pointer">
                    <input type="checkbox" name="remove_image" value="1" @checked(old('remove_image')) class="h-4 w-4 accent-brand">
                    Hapus gambar saat disimpan
                </label>
            </div>
        @endif

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="image" class="block text-sm font-medium mb-1.5">Unggah gambar</label>
                <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="block w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-sm file:font-semibold dark:file:bg-slate-800">
                <p class="mt-1 text-xs text-slate-400">JPG, PNG, atau WEBP. Maksimal 5 MB.</p>
                @error('image') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="image_path" class="block text-sm font-medium mb-1.5">URL/path gambar</label>
                <input id="image_path" name="image_path" type="text" maxlength="255" value="{{ old('image_path', $item?->image_path) }}" placeholder="https://... atau images/banner.webp"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
                @error('image_path') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="sm:col-span-2">
        <label for="target_url" class="block text-sm font-medium mb-1.5">Tautan tujuan <span class="font-normal text-slate-400">(opsional)</span></label>
        <input id="target_url" name="target_url" type="url" maxlength="500" value="{{ old('target_url', $item?->target_url) }}" placeholder="https://contoh.banyumaskab.go.id/informasi"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        <p class="mt-1 text-xs text-slate-400">Kosongkan apabila banner hanya bersifat informatif.</p>
        @error('target_url') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="starts_at" class="block text-sm font-medium mb-1.5">Mulai tayang <span class="font-normal text-slate-400">(opsional)</span></label>
        <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $item?->starts_at?->format('Y-m-d\TH:i')) }}"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        <p class="mt-1 text-xs text-slate-400">Kosong berarti dapat tayang segera.</p>
        @error('starts_at') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="ends_at" class="block text-sm font-medium mb-1.5">Selesai tayang <span class="font-normal text-slate-400">(opsional)</span></label>
        <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $item?->ends_at?->format('Y-m-d\TH:i')) }}"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        <p class="mt-1 text-xs text-slate-400">Kosong berarti tanpa batas waktu.</p>
        @error('ends_at') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="sort_order" class="block text-sm font-medium mb-1.5">Urutan tampil</label>
        <input id="sort_order" name="sort_order" type="number" min="0" max="999999" required value="{{ old('sort_order', $item?->sort_order ?? 0) }}"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        <p class="mt-1 text-xs text-slate-400">Angka lebih kecil tampil lebih dahulu.</p>
        @error('sort_order') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-end pb-2">
        <label class="flex items-center gap-2 text-sm font-medium cursor-pointer">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $item?->is_active ?? true)) class="h-4 w-4 accent-brand">
            Aktifkan banner
        </label>
    </div>
</div>
