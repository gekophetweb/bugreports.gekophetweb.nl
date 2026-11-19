@props(['label', 'name', 'hint' => null])
<div {{ $attributes->class('space-y-1') }}>
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
