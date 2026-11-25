@php
    $checked = collect($rememberOld ? old('case_ids', $selectedIds) : $selectedIds)
        ->filter(fn ($id) => $id !== null && $id !== '')
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp
<div x-data="{ open: false, count: {{ count($checked) }} }" @click.outside.capture="open = false">
    <span class="block text-xs font-semibold text-gray-500 mb-1">Cases they can open</span>
    <div class="relative" :class="open ? 'z-30' : ''">
        <button type="button" @click="open = !open" class="w-full bg-white border border-gray-300 rounded-lg px-3 py-3 text-sm text-left text-gray-800" :aria-expanded="open">
            <span x-text="count === 0 ? 'Select cases' : (count === 1 ? '1 case selected' : count + ' cases selected')"></span>
        </button>
        <div x-show="open" x-cloak class="absolute z-30 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white p-2 shadow-lg" style="top: calc(100% + 0.25rem); left: 0; right: 0; height: 12rem;">
            <input type="hidden" name="case_ids[]" value="">
            @forelse ($cases as $case)
                <label class="flex items-start gap-2 px-2 py-2 text-sm cursor-pointer">
                    <input type="checkbox" name="case_ids[]" value="{{ $case->id }}" class="mt-1" @checked(in_array($case->id, $checked, true)) @change="count = $root.querySelectorAll('input[type=checkbox]:checked').length">
                    <span class="min-w-0">
                        <span class="block font-semibold truncate">{{ $case->title ?: 'Untitled case' }}</span>
                        @if ($case->parties_label)
                            <span class="block text-xs text-gray-500 truncate">{{ $case->parties_label }}</span>
                        @endif
                    </span>
                </label>
            @empty
                <p class="px-2 py-2 text-sm text-gray-500">You have no cases yet.</p>
            @endforelse
        </div>
    </div>
    <p class="mt-1 text-xs text-gray-500">After they sign in, they only see the cases you check.</p>
</div>
