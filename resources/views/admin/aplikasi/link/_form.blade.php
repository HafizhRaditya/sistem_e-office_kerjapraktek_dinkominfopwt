@php $lnk = $link ?? null; @endphp

@if ($errors->any())
    <div class="mb-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-brand font-medium">
        {{ $errors->first() }}
    </div>
@endif

<div class="space-y-5">
    <div>
        <label for="label" class="block text-sm font-medium mb-1.5">Label</label>
        <input id="label" name="label" type="text" value="{{ old('label', $lnk?->label) }}" placeholder="contoh: Backend, Frontend, Backend V2"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        <p class="mt-1 text-xs text-slate-400">Harus unik di dalam aplikasi ini.</p>
        @error('label') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="url" class="block text-sm font-medium mb-1.5">URL tujuan</label>
        <input id="url" name="url" type="url" value="{{ old('url', $lnk?->url) }}" placeholder="https://…"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        @error('url') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="sort_order" class="block text-sm font-medium mb-1.5">Urutan</label>
        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $lnk?->sort_order ?? 0) }}"
            class="w-40 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        @error('sort_order') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $lnk?->is_active ?? true)) class="w-4 h-4 accent-brand">
        Aktif (dapat diluncurkan)
    </label>
</div>
