<div class="feed-layout">
    <aside class="feed-layout__rail">
        {{-- Collapsed on phones so the issues come first; always open from the lg breakpoint. --}}
        <details class="rail-toggle" wire:ignore.self x-data x-init="if (matchMedia('(min-width: 1024px)').matches) $el.open = true">
            @php $active = count($filters['languages']) + ($filters['severity'] ? 1 : 0) + ($filters['period'] ? 1 : 0) + ($filters['q'] !== '' ? 1 : 0); @endphp
            <summary class="rail-toggle__summary btn-secondary w-full">Filters{{ $active ? " ($active active)" : '' }}</summary>
            <x-issue-filters :all-languages="$allLanguages" :severities="$severities" :periods="$periods" :filters="$filters">
                @if ($myToggles ?? false)
                    <fieldset class="filters__group">
                        <legend class="filters__title">Show</legend>
                        <ul class="space-y-1 text-sm">
                            <li><label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="subscribed" class="size-4"> Subscribed languages</label></li>
                            <li><label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="watched" class="size-4"> Watched issues</label></li>
                            <li><label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="starred" class="size-4"> Starred issues</label></li>
                        </ul>
                        <a href="{{ route('my.settings') }}" wire:navigate class="mt-2 inline-block text-sm text-[--signal] underline underline-offset-2">Manage subscriptions</a>
                    </fieldset>
                @endif
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

        @if ($followsNothing ?? false)
            <div class="empty">
                <p class="font-medium">You are not following anything yet.</p>
                <p class="mt-1 text-sm text-[--muted]">Subscribe to the languages and servers you use, or star and watch single issues.</p>
                <a href="{{ route('my.settings') }}" wire:navigate class="btn-primary mt-4">Choose languages</a>
            </div>
        @elseif ($issues->isEmpty())
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
