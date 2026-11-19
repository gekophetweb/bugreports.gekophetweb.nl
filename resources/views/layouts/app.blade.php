<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ isset($title) ? $title.' | ' : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=archivo:400,500,600,700|archivo-narrow:600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-[--surface] focus:px-3 focus:py-2">Skip to content</a>
    <header class="border-b border-[--line] bg-[--surface]">
        <nav class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3" aria-label="Main">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 font-semibold">
                <svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><rect x="1" y="1" width="4" height="18" rx="1" fill="var(--sev-critical)"/><rect x="7" y="5" width="4" height="14" rx="1" fill="var(--sev-high)"/><rect x="13" y="10" width="4" height="9" rx="1" fill="var(--sev-medium)"/></svg>
                {{ config('app.name') }}
            </a>
            <div class="ml-auto flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                @if (Route::has('feedback.create'))
                    <a href="{{ route('feedback.create') }}" wire:navigate>Feedback</a>
                @endif
                @auth
                    @if (Route::has('my.issues'))
                        <a href="{{ route('my.issues') }}" wire:navigate>My issues</a>
                    @endif
                    @if (Route::has('my.settings'))
                        <a href="{{ route('my.settings') }}" wire:navigate>Settings</a>
                    @endif
                    @if (auth()->user()->isSuperadmin())
                        <a href="{{ route('admin.mail') }}" wire:navigate>Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="underline underline-offset-2">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="btn-secondary py-1.5">Sign in</a>
                @endauth
            </div>
        </nav>
    </header>
    <main id="main" class="mx-auto max-w-7xl px-4">
        {{ $slot }}
    </main>
    <footer class="mx-auto max-w-7xl px-4 py-10 text-xs text-[--muted]">
        Issues come from <a class="underline" href="https://osv.dev" rel="noopener">OSV.dev</a> (including GitHub Security Advisories)
        and the <a class="underline" href="https://nvd.nist.gov" rel="noopener">NVD</a>. The source is always the authority.
    </footer>
</body>
</html>
