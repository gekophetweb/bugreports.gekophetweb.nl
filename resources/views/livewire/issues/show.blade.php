<article class="detail">
    <a href="{{ route('home') }}" wire:navigate class="text-sm text-[--signal] underline underline-offset-2">All issues</a>

    <header class="detail__head">
        <x-severity-score :issue="$issue" size="lg" />
        <div class="min-w-0">
            <p class="detail__id">
                {{ $issue->external_id }}
                @if ($issue->withdrawn_at)
                    <span class="withdrawn">Withdrawn {{ $issue->withdrawn_at->inDisplayTz()->format('j M Y') }}</span>
                @endif
            </p>
            <h1 class="detail__title">{{ $issue->title }}</h1>
            <p class="mt-3 flex flex-wrap gap-2">
                @foreach ($issue->languages as $language)<span class="tag">{{ $language->name }}</span>@endforeach
            </p>
        </div>
    </header>

    <div class="detail__actions">
        <a href="{{ $issue->source_url }}" target="_blank" rel="noopener noreferrer" class="btn-primary">Read the full report at the source</a>
        <livewire:issues.star-toggle :issue="$issue" />
        <livewire:issues.watch-toggle :issue="$issue" />
    </div>

    <div class="detail__grid">
        <div class="detail__text">
            @if ($issue->description)
                <div class="prose-text">{!! nl2br(e($issue->description)) !!}</div>
            @else
                <p class="text-[--muted]">The source has no description for this issue.</p>
            @endif
        </div>

        <dl class="facts">
            <div><dt>Published</dt><dd>{{ $issue->published_at?->inDisplayTz()->format('j M Y H:i') ?? 'Unknown' }}</dd></div>
            <div><dt>Last updated</dt><dd>{{ $issue->modified_at?->inDisplayTz()->format('j M Y H:i') ?? 'Unknown' }}</dd></div>
            @if ($issue->cvss_vector)
                <div><dt>CVSS vector</dt><dd class="break-all">{{ $issue->cvss_vector }}</dd></div>
            @endif
            @if ($issue->aliases)
                <div><dt>Also known as</dt><dd>{{ implode(', ', $issue->aliases) }}</dd></div>
            @endif
            @if ($issue->packages)
                <div><dt>Affected packages</dt><dd>
                    <ul class="space-y-1">
                        @foreach ($issue->packages as $package)
                            <li>{{ $package }}
                                @auth
                                    @if (\App\Support\Issues\HiddenPackages::hides(auth()->user()->hiddenPackages(), [$package]))
                                        <span class="text-xs text-[--muted]">(hidden)</span>
                                    @else
                                        <button type="button" wire:click="hidePackage(@js($package))" class="row__hide"
                                                title="Hide issues of {{ $package }} from your lists and emails">Hide</button>
                                    @endif
                                @endauth
                            </li>
                        @endforeach
                    </ul>
                    <x-status />
                </dd></div>
            @endif
        </dl>
    </div>

    @if ($issue->references)
        <section class="mt-10">
            <h2 class="text-lg font-semibold">References</h2>
            <ul class="refs">
                @foreach (\App\Support\SafeUrls::filter($issue->references) as $url)
                    <li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer nofollow" class="break-all">{{ $url }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</article>
