@props(['label', 'name', 'hint' => null])
<div {{ $attributes->class(['space-y-1', 'field--error' => $errors->has($name)]) }}>
    <label for="{{ $name }}" class="block text-sm font-medium">{{ $label }}</label>
    {{-- Hints sit above the control so autocomplete popups and phone keyboards do not cover them. --}}
    @if ($hint)
        <p class="text-xs text-(--muted)">{{ $hint }}</p>
    @endif
    {{ $slot }}
    @error($name)
        <p class="field__error"><span aria-hidden="true">⚠</span> {{ $message }}</p>
    @enderror
</div>
