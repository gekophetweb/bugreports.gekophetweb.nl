@props(['issue', 'dateField' => 'published'])
@php $date = $dateField === 'modified' ? $issue->modified_at : $issue->published_at; @endphp
<li class="row" wire:key="issue-{{ $issue->id }}">
    <x-severity-score :issue="$issue" />
    <div class="row__body">
        <p class="row__id">
            <a href="{{ route('issues.show', $issue) }}" wire:navigate class="row__link">{{ $issue->external_id }}</a>
            @foreach (array_slice($issue->aliases ?? [], 0, 2) as $alias)
                <span class="row__alias">{{ $alias }}</span>
            @endforeach
        </p>
        <p class="row__title">{{ $issue->title }}</p>
        <p class="row__meta">
            @foreach ($issue->languages as $language)<span class="tag">{{ $language->name }}</span>@endforeach
            @if ($issue->packages)
                <span class="row__pkgs">{{ implode(', ', array_slice($issue->packages, 0, 3)) }}@if (count($issue->packages) > 3) and {{ count($issue->packages) - 3 }} more @endif</span>
                {{-- One package: hide it right here. Several: per package on the issue page. --}}
                @if (count($issue->packages) === 1)
                    <button type="button" wire:click="hidePackage(@js($issue->packages[0]))" class="row__hide"
                            title="Hide issues of {{ $issue->packages[0] }} from your lists and emails">Hide</button>
                @endif
            @endif
        </p>
    </div>
    <div class="row__side">
        @php $starred = (bool) ($issue->is_starred ?? false); @endphp
        <button type="button" wire:click="toggleStar({{ $issue->id }})" class="star" aria-pressed="{{ $starred ? 'true' : 'false' }}"
                aria-label="{{ $starred ? 'Unstar' : 'Star' }} {{ $issue->external_id }}" title="Starred issues are shown first">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" fill="{{ $starred ? 'currentColor' : 'none' }}"
                 stroke="currentColor" stroke-width="2"><path d="M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9z"/></svg>
        </button>
        @if ($date)
            <time datetime="{{ $date->toIso8601String() }}" class="row__date" title="{{ $date->inDisplayTz()->format('j M Y H:i') }}">
                {{ $date->inDisplayTz()->isToday() ? $date->inDisplayTz()->format('H:i') : $date->inDisplayTz()->format('j M Y') }}
            </time>
        @endif
        <a href="{{ $issue->source_url }}" target="_blank" rel="noopener noreferrer" class="row__source">Source</a>
    </div>
</li>
