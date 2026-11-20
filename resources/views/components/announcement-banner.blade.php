@props(['announcements'])
@if ($announcements->isNotEmpty())
    <div class="announce" role="region" aria-label="Security announcements">
        @foreach ($announcements as $a)
            @php $language = $a->language->name; $severity = $a->severity ? ucfirst($a->severity).' ' : ''; @endphp
            <div class="announce__item {{ $a->kind === \App\Enums\AnnouncementKind::Upcoming && ! $a->isOut() ? 'announce__item--soon' : '' }}">
                @if ($a->kind === \App\Enums\AnnouncementKind::Upcoming)
                    @if ($a->isOut())
                        @php $versions = $a->releaseVersions(); @endphp
                        <p class="announce__title">{{ $severity }}{{ $language }} security release is out.
                            @if ($versions) Update to {{ implode(', ', $versions) }}. @endif</p>
                    @else
                        <p class="announce__title">{{ $severity }}{{ $language }} security release expected {{ $a->windowLabel() }}</p>
                        <p class="announce__meta">{{ $a->localWindowLabel() }}. Details and fixed versions come with the release. Check your versions and plan an update.</p>
                    @endif
                @else
                    <p class="announce__title">{{ $a->title }}</p>
                    <p class="announce__meta">{{ $language }} · {{ $a->published_at->inDisplayTz()->format('j M') }}</p>
                @endif
                <a href="{{ $a->url }}" target="_blank" rel="noopener noreferrer" class="announce__link">Read it on php.net</a>
            </div>
        @endforeach
    </div>
@endif
