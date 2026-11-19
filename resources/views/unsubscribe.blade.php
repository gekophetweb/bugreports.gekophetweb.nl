@component('layouts.app', ['title' => 'Unsubscribe'])
    <div class="mx-auto max-w-md py-16">
        @if ($done)
            <h1 class="text-2xl font-semibold">You are unsubscribed</h1>
            <p class="mt-2 text-[--muted]">You will no longer get {{ $what }}. You can follow it again from your settings.</p>
        @else
            <h1 class="text-2xl font-semibold">Stop {{ $what }}?</h1>
            <form method="POST" action="{{ $action }}" class="mt-6">
                <button type="submit" class="btn-primary">Unsubscribe</button>
            </form>
        @endif
        @auth
            <p class="mt-6 text-sm"><a href="{{ route('my.settings') }}" class="underline">Email settings</a></p>
        @endauth
    </div>
@endcomponent
