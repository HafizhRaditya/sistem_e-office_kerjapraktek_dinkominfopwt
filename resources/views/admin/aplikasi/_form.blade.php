@php $app = $application ?? null; @endphp

@if ($errors->any())
    <div class="mb-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-brand font-medium">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid sm:grid-cols-2 gap-5">
    <div class="sm:col-span-2">
        <label for="name" class="block text-sm font-medium mb-1.5">Nama aplikasi</label>
        <input id="name" name="name" type="text" value="{{ old('name', $app?->name) }}"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        @error('name') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="opd_id" class="block text-sm font-medium mb-1.5">OPD pemilik</label>
        <select id="opd_id" name="opd_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
            <option value="">— pilih OPD —</option>
            @foreach ($opds as $opd)
                <option value="{{ $opd->id }}" @selected((string) old('opd_id', $app?->opd_id) === (string) $opd->id)>{{ $opd->name }}</option>
            @endforeach
        </select>
        @error('opd_id') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="slug" class="block text-sm font-medium mb-1.5">Slug</label>
        <input id="slug" name="slug" type="text" value="{{ old('slug', $app?->slug) }}" placeholder="contoh: e-planning"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm font-mono focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        <p class="mt-1 text-xs text-slate-400">Huruf kecil, angka, dan tanda hubung. Harus unik.</p>
        @error('slug') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="app_group" class="block text-sm font-medium mb-1.5">Grup aplikasi</label>
        <select id="app_group" name="app_group" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
            <option value="">— pilih grup —</option>
            @foreach ($appGroups as $g)
                <option value="{{ $g }}" @selected(old('app_group', $app?->app_group) === $g)>{{ ['smartcity' => 'Smart City', 'spbe' => 'SPBE', 'tools' => 'Tools'][$g] }}</option>
            @endforeach
        </select>
        @error('app_group') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="category" class="block text-sm font-medium mb-1.5">Kategori <span class="text-slate-400 font-normal">(opsional)</span></label>
        <select id="category" name="category" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm capitalize focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
            <option value="">— tanpa kategori —</option>
            @foreach ($categories as $c)
                <option value="{{ $c }}" @selected(old('category', $app?->category) === $c)>{{ ucfirst($c) }}</option>
            @endforeach
        </select>
        @error('category') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="sort_order" class="block text-sm font-medium mb-1.5">Urutan</label>
        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $app?->sort_order ?? 0) }}"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        @error('sort_order') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="description" class="block text-sm font-medium mb-1.5">Deskripsi <span class="text-slate-400 font-normal">(opsional)</span></label>
        <textarea id="description" name="description" rows="3"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">{{ old('description', $app?->description) }}</textarea>
        @error('description') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2 flex flex-wrap gap-6">
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $app?->is_active ?? true)) class="w-4 h-4 accent-brand">
            Aktif
        </label>
        <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" name="is_new" value="1" @checked(old('is_new', $app?->is_new ?? false)) class="w-4 h-4 accent-brand">
            Tandai sebagai "Baru"
        </label>
    </div>
</div>
