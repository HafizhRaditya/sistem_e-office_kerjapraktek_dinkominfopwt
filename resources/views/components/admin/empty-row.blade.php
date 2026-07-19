{{--
    Empty state for an admin table body.

    Distinguishes the two cases a blank table can mean, because the fix differs:
      - `filtered` = the search/filter excluded everything -> tell the admin to
        widen the search;
      - otherwise  = there is genuinely no data yet -> point at how to add some.

    Anonymous Blade component (no ⚡ prefix), so Livewire's single-file component
    discovery in this directory leaves it alone.
--}}
@props([
    'colspan' => 1,
    'filtered' => false,
    'title' => 'Belum ada data',
    'hint' => null,
    'filteredTitle' => 'Tidak ada hasil yang cocok',
    'filteredHint' => 'Coba kata kunci lain, atau reset filter untuk melihat semua data.',
])

<tr>
    <td colspan="{{ $colspan }}" class="px-5 py-12 text-center">
        <span class="material-symbols-outlined text-slate-300 dark:text-slate-600" style="font-size:40px" aria-hidden="true">{{ $filtered ? 'search_off' : 'inbox' }}</span>

        <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">{{ $filtered ? $filteredTitle : $title }}</p>

        @php $sub = $filtered ? $filteredHint : $hint; @endphp
        @if ($sub)
            <p class="mt-1 text-xs text-slate-400">{{ $sub }}</p>
        @endif

        @if (! $filtered && trim($slot) !== '')
            <div class="mt-4">{{ $slot }}</div>
        @endif
    </td>
</tr>
