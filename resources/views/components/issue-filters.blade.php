@props(['allLanguages', 'severities', 'periods', 'filters'])
<form class="filters" wire:submit.prevent role="search" aria-label="Filter issues">
    <div class="filters__group">
        <label for="q" class="filters__title">Search</label>
        <input id="q" type="search" wire:model.live.debounce.400ms="q" class="input" placeholder="CVE, package or words">
    </div>

    <fieldset class="filters__group">
        <legend class="filters__title">Severity at least</legend>
        <div class="seg">
            <label class="seg__item"><input type="radio" wire:model.live="severity" value=""><span>Any</span></label>
            @foreach ($severities as $s)
                <label class="seg__item seg__item--{{ $s->value }}"><input type="radio" wire:model.live="severity" value="{{ $s->value }}"><span>{{ $s->label() }}</span></label>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="filters__group">
        <legend class="filters__title">Date</legend>
        <select id="period" wire:model.live="period" class="input" aria-label="Period">
            <option value="">Any time</option>
            @foreach ($periods as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        @if ($filters['period'] === 'custom')
            <div class="mt-2 grid grid-cols-2 gap-2">
                <label class="text-xs text-[--muted]">From<input type="date" wire:model.live="from" class="input mt-1"></label>
                <label class="text-xs text-[--muted]">To<input type="date" wire:model.live="to" class="input mt-1"></label>
            </div>
        @endif
        @if ($filters['period'])
            <div class="mt-2 flex gap-3 text-sm">
                <label class="inline-flex items-center gap-1.5"><input type="radio" wire:model.live="date_field" value="published"> Published</label>
                <label class="inline-flex items-center gap-1.5"><input type="radio" wire:model.live="date_field" value="modified"> Updated</label>
            </div>
        @endif
    </fieldset>

    <fieldset class="filters__group">
        <legend class="filters__title">Languages and servers</legend>
        <ul class="space-y-1">
            @foreach ($allLanguages as $language)
                <li>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="languages" value="{{ $language->slug }}" class="size-4">
                        {{ $language->name }}
                    </label>
                </li>
            @endforeach
        </ul>
    </fieldset>

    {{ $slot }}

    <button type="button" wire:click="clearFilters" class="text-sm text-[--signal] underline underline-offset-2">Clear filters</button>
</form>
