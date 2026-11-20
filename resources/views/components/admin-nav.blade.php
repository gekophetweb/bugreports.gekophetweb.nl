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
<nav class="admin-nav" aria-label="Admin">
    @foreach ($links as $route => $label)
        @if (Route::has($route))
            <a href="{{ route($route) }}" class="admin-nav__link" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
        @endif
    @endforeach
</nav>
