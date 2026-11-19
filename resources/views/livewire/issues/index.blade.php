<div class="feed-layout">
    <aside class="feed-layout__rail">
        {{-- Collapsed on phones so the issues come first; always open from the lg breakpoint. --}}
        <details class="rail-toggle" wire:ignore.self x-data x-init="if (matchMedia('(min-width: 1024px)').matches) $el.open = true">
            @php $active = count($filters['languages']) + ($filters['severity'] ? 1 : 0) + ($filters['period'] ? 1 : 0) + ($filters['q'] !== '' ? 1 : 0); @endphp
            <summary class="rail-toggle__summary btn-secondary w-full">Filters{{ $active ? " ($active active)" : '' }}</summary>
            <x-issue-filters :all-languages="$allLanguages" :severities="$severities" :periods="$periods" :filters="$filters">
                {{ $extraFilters ?? '' }}
            </x-issue-filters>
        </details>
    </aside>

    <section class="feed-layout__main" aria-labelledby="feed-heading">
        <header class="feed-head">
            <h1 id="feed-heading" class="feed-head__title">{{ $heading }}</h1>
            <p class="feed-head__count" wire:loading.class="opacity-50">
                {{ number_format($issues->total()) }} {{ Str::plural('issue', $issues->total()) }}
            </p>
        </header>

        @if ($issues->isEmpty())
            <div class="empty">
                <p class="font-medium">No issues match these filters.</p>
                <p class="mt-1 text-sm text-[--muted]">Widen the date range or pick fewer filters. New issues are checked every few minutes.</p>
            </div>
        @else
            <ol class="feed" wire:loading.class="opacity-60">
                @foreach ($issues as $issue)
                    <x-issue-row :issue="$issue" :date-field="$filters['date_field']" />
                @endforeach
            </ol>
            <div class="mt-6">{{ $issues->links() }}</div>
        @endif
    </section>
</div>
