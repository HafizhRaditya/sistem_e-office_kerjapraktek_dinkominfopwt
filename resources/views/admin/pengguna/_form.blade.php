@php
    $u = $user ?? null;
    $self = $isSelf ?? false;
    $roleLabels = ['admin' => 'Admin', 'pegawai' => 'Pegawai'];
@endphp

@if ($errors->any())
    <div class="mb-5 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-brand font-medium">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid sm:grid-cols-2 gap-5">
    <div>
        <label for="name" class="block text-sm font-medium mb-1.5">Nama lengkap</label>
        <input id="name" name="name" type="text" value="{{ old('name', $u?->name) }}"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        @error('name') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="nip_nik" class="block text-sm font-medium mb-1.5">NIP / NIK</label>
        <input id="nip_nik" name="nip_nik" type="text" value="{{ old('nip_nik', $u?->nip_nik) }}"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm font-mono focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        <p class="mt-1 text-xs text-slate-400">Dipakai untuk login. Harus unik.</p>
        @error('nip_nik') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium mb-1.5">Email <span class="text-slate-400 font-normal">(opsional)</span></label>
        <input id="email" name="email" type="email" value="{{ old('email', $u?->email) }}"
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        @error('email') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="opd_id" class="block text-sm font-medium mb-1.5">OPD</label>
        <select id="opd_id" name="opd_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand">
            <option value="">— pilih OPD —</option>
            @foreach ($opds as $opd)
                <option value="{{ $opd->id }}" @selected((string) old('opd_id', $u?->opd_id) === (string) $opd->id)>{{ $opd->name }}</option>
            @endforeach
        </select>
        @error('opd_id') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="role" class="block text-sm font-medium mb-1.5">Peran</label>
        <select id="role" name="role" @disabled($self)
            class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand disabled:opacity-50 disabled:cursor-not-allowed">
            @foreach ($roles as $r)
                <option value="{{ $r }}" @selected(old('role', $u?->role) === $r)>{{ $roleLabels[$r] ?? $r }}</option>
            @endforeach
        </select>
        @if ($self)
            <p class="mt-1 text-xs text-slate-400">Peran akun sendiri tidak dapat diubah.</p>
        @endif
        @error('role') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
    </div>

    @if (! $u)
        <div>
            <label for="password" class="block text-sm font-medium mb-1.5">Kata sandi</label>
            <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Min. 8 karakter, huruf & angka"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
            @error('password') <p class="mt-1 text-xs text-brand">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium mb-1.5">Ulangi kata sandi</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2.5 text-sm focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15">
        </div>
    @endif

    <div class="sm:col-span-2">
        <label class="flex items-center gap-2 text-sm {{ $self ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }}">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $u?->is_active ?? true)) @disabled($self) class="w-4 h-4 accent-brand">
            Akun aktif (dapat login)
        </label>
        @if ($self)
            <p class="mt-1 text-xs text-slate-400">Anda tidak dapat menonaktifkan akun sendiri.</p>
        @endif
    </div>
</div>
