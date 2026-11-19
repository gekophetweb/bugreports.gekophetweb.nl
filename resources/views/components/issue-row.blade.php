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
            @endif
        </p>
    </div>
    <div class="row__side">
        {{ $actions ?? '' }}
        @if ($date)
            <time datetime="{{ $date->toIso8601String() }}" class="row__date" title="{{ $date->inDisplayTz()->format('j M Y H:i') }}">
                {{ $date->inDisplayTz()->isToday() ? $date->inDisplayTz()->format('H:i') : $date->inDisplayTz()->format('j M Y') }}
            </time>
        @endif
        <a href="{{ $issue->source_url }}" target="_blank" rel="noopener noreferrer" class="row__source">Source</a>
    </div>
</li>
