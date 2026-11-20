@props(['value', 'label'])
{{-- A value to paste somewhere else, with a copy button. --}}
<div class="mt-1 flex items-center gap-2" x-data="{ copied: false }">
    <code class="min-w-0 flex-1 select-all break-all rounded border border-(--line) bg-(--paper) px-2 py-1 text-sm">{{ $value }}</code>
    <button type="button" class="btn-secondary shrink-0 px-2 py-1 text-xs" aria-label="Copy {{ $label }}"
            x-on:click="navigator.clipboard.writeText(@js($value)); copied = true; setTimeout(() => copied = false, 1500)">
        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
    </button>
</div>
