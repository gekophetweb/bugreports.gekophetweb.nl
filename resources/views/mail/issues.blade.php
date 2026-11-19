@php
    $fieldLabels = ['severity' => 'severity', 'cvss_score' => 'score', 'description' => 'description', 'references' => 'references', 'withdrawn_at' => 'withdrawn'];
    $score = fn ($issue) => $issue->cvss_score !== null ? number_format($issue->cvss_score, 1).' ' : '';
@endphp
<x-mail::message>
{{ $intro }}

@if ($digest->watched)
## Updated
@foreach ($digest->watched as $item)
**[{{ $item['issue']->external_id }}]({{ route('issues.show', $item['issue']) }})** {{ $item['issue']->title }}<br>
{{ $score($item['issue']) }}{{ $item['issue']->severity->label() }}@if ($item['previous'] !== $item['issue']->severity) (was {{ $item['previous']->label() }})@endif.
Changed: {{ collect($item['fields'])->map(fn ($f) => $fieldLabels[$f] ?? $f)->implode(', ') ?: 'details' }}.
@if (($item['tag'] ?? '') === 'critical') You get this because it is Critical in a language you follow.@endif
@if (! empty($item['unsubscribeUrl'])) [{{ $item['tag'] === 'watched' ? 'Stop watching' : 'Unsubscribe from this language' }}]({{ $item['unsubscribeUrl'] }})@endif


@endforeach
@endif

@if ($digest->full)
## New issues
@foreach ($digest->full as $item)
**{{ $score($item['issue']) }}{{ $item['issue']->severity->label() }}** · [{{ $item['issue']->external_id }}]({{ route('issues.show', $item['issue']) }}){{ $item['alerted'] ? ' (already alerted)' : '' }}<br>
{{ $item['issue']->title }}<br>
{{ $item['issue']->languages->pluck('name')->implode(', ') }}@if ($item['issue']->packages): {{ implode(', ', array_slice($item['issue']->packages, 0, 3)) }}@endif


@endforeach
@endif

@if ($digest->brief)
## More new issues
@foreach ($digest->brief as $item)
- {{ $item['issue']->severity->label() }}: [{{ $item['issue']->external_id }}]({{ route('issues.show', $item['issue']) }}) {{ str($item['issue']->title)->limit(90) }}
@endforeach
@endif

@if ($digest->overflowCount > 0)
…and {{ $digest->overflowCount }} more. [See all of them on the site]({{ $digest->moreUrl }}).
@endif

<x-mail::button :url="$digest->moreUrl">See them on the site</x-mail::button>

<x-mail::subcopy>
@foreach ($subscriptions as $sub)
You follow {{ $sub->language->name }} ({{ $sub->frequency->label() }}, {{ $sub->min_severity->label() }} and up). [Unsubscribe]({{ $sub->unsubscribeUrl() }})<br>
@endforeach
[Email settings]({{ route('my.settings') }})
</x-mail::subcopy>
</x-mail::message>
