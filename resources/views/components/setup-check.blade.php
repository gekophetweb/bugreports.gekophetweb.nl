@props(['done', 'href' => null, 'optional' => false])
<li class="flex items-start gap-3 py-2">
    @if ($done)
        <svg class="mt-0.5 shrink-0" width="18" height="18" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="9" fill="#15803d"/><path d="M6 10.5l2.5 2.5L14 7.5" stroke="#fff" stroke-width="2" fill="none"/></svg>
    @else
        <svg class="mt-0.5 shrink-0" width="18" height="18" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none" opacity=".5"/></svg>
    @endif
    <div class="min-w-0">
        <p>
            {{ $slot }}
            <span class="sr-only">{{ $done ? '(done)' : ($optional ? '(optional, not set up)' : '(not set up yet)') }}</span>
            @if (! $done && $optional)<span class="text-sm text-(--muted)">Optional</span>@endif
        </p>
        @isset($hint)<p class="text-sm text-(--muted)">{{ $hint }}</p>@endisset
    </div>
    @if ($href)
        <a href="{{ $href }}" class="ml-auto shrink-0 text-sm text-(--signal) underline underline-offset-2">{{ $done ? 'Change' : 'Set up' }}</a>
    @endif
</li>
