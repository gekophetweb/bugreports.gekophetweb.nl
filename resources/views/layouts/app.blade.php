<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <nav class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3">
            <a href="{{ url('/') }}" class="font-semibold">{{ config('app.name') }}</a>
            <div class="ml-auto flex items-center gap-4 text-sm">
                @auth
                    @if (auth()->user()->isSuperadmin())
                        <a href="{{ route('admin.mail') }}">Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}">Sign in</a>
                @endauth
            </div>
        </nav>
    </header>
    <main class="mx-auto max-w-6xl px-4">
        {{ $slot }}
    </main>
</body>
</html>
