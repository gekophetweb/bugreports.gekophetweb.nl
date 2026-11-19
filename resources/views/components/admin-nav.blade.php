@php
    $links = [
        'admin.setup' => 'Setup',
        'admin.mail' => 'Mail',
        'admin.sign-in' => 'Sign-in',
        'admin.users' => 'Users',
        'admin.languages' => 'Languages',
        'admin.sync' => 'Sync',
        'admin.feedback' => 'Feedback',
    ];
@endphp
<nav class="flex flex-wrap gap-2 py-6 text-sm" aria-label="Admin">
    @foreach ($links as $route => $label)
        @if (Route::has($route))
            <a href="{{ route($route) }}" @class([
                'rounded-md px-3 py-1.5',
                'bg-indigo-600 text-white' => request()->routeIs($route),
                'hover:bg-zinc-200 dark:hover:bg-zinc-800' => ! request()->routeIs($route),
            ])>{{ $label }}</a>
        @endif
    @endforeach
</nav>
