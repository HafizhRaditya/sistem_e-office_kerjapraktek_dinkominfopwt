@php
    $app = $application ?? null;
    $iconValue = old('icon_path', $app?->icon);
    $iconPreview = $iconValue
        ? ((str_starts_with($iconValue, 'http://') || str_starts_with($iconValue, 'https://')) ? $iconValue : asset($iconValue))
        : null;
@endphp

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

    <div class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-950/40"
        x-data="{
            preview: @js($iconPreview),
            original: @js($iconPreview),
            baseUrl: @js(url('/')),
            removed: @js((bool) old('remove_icon', false)),
            previewFile(event) {
                const file = event.target.files?.[0];
                if (! file) return;

                this.removed = false;
                const reader = new FileReader();
                reader.onload = (e) => this.preview = e.target?.result;
                reader.readAsDataURL(file);
            },
            previewPath(value) {
                this.removed = false;
                value = value.trim();
                this.preview = value
                    ? (/^https?:\/\//i.test(value) ? value : this.baseUrl + '/' + value.replace(/^\/+/, ''))
                    : null;
            },
        }">
        <div class="flex flex-col gap-4 sm:flex-row">
            <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <template x-if="preview && ! removed">
                    <img :src="preview" alt="Pratinjau ikon aplikasi" class="h-full w-full object-contain p-2">
                </template>
                <template x-if="! preview || removed">
                    <span class="material-symbols-outlined text-4xl text-slate-300">apps</span>
                </template>
            </div>

            <div class="grid min-w-0 flex-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="icon_file" class="block text-sm font-medium mb-1.5">Unggah ikon</label>
                    <input id="icon_file" name="icon_file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        @change="previewFile($event)"
                        class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:border-0 file:border-r file:border-slate-200 file:bg-slate-50 file:px-3 file:py-2.5 file:text-sm file:font-medium dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:file:border-slate-700 dark:file:bg-slate-800">
                    <p class="mt-1 text-xs text-slate-400">JPG, PNG, atau WEBP. Maksimal 5 MB. Disarankan rasio 1:1.</p>
                    @error('icon_file') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="icon_path" class="block text-sm font-medium mb-1.5">URL/path ikon <span class="font-normal text-slate-400">(opsional)</span></label>
                    <input id="icon_path" name="icon_path" type="text" value="{{ old('icon_path', $app?->icon) }}"
                        placeholder="https://... atau images/applications/icon.webp"
                        @input="previewPath($event.target.value)"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/15 dark:border-slate-700 dark:bg-slate-900">
                    <p class="mt-1 text-xs text-slate-400">Unggahan baru menjadi pilihan utama.</p>
                    @error('icon_path') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
                </div>

                @if ($app?->icon)
                    <label class="flex cursor-pointer items-center gap-2 text-sm sm:col-span-2">
                        <input type="checkbox" name="remove_icon" value="1" x-model="removed"
                            @change="preview = removed ? null : original"
                            class="h-4 w-4 accent-brand">
                        Hapus ikon saat ini
                    </label>
                @endif
            </div>
        </div>
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
