@props(['issue', 'size' => 'md'])
@php
    $sev = $issue->severity->value;
    $score = $issue->cvss_score !== null ? number_format($issue->cvss_score, 1) : null;
@endphp
{{-- The one loud element: the score numeral in its severity colour, with the severity spelled out underneath. --}}
<div {{ $attributes->class(['score', "score--$sev", "score--$size"]) }}>
    <span class="score__num" aria-hidden="{{ $score ? 'false' : 'true' }}">{{ $score ?? '–' }}</span>
    <span class="score__label">{{ $issue->severity->label() }}@if ($score)<span class="sr-only">, CVSS {{ $score }}</span>@endif</span>
</div>
