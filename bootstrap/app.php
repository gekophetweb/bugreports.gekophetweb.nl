<?php

use App\Http\Middleware\ApplyRuntimeSettings;
use App\Http\Middleware\EnsureSuperadmin;
use App\Support\LocalEnvironment;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Settings this installation wrote itself (storage/app/.env.local) win over .env. Tests use phpunit.xml only.
if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
    LocalEnvironment::load(dirname(__DIR__));
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['superadmin' => EnsureSuperadmin::class]);

        $middleware->web(append: [ApplyRuntimeSettings::class]);

        // Mail clients' one-click unsubscribe POSTs without a CSRF token; the URL is signed instead.
        $middleware->validateCsrfTokens(except: ['unsubscribe/*']);

        // Which proxies are trusted comes from config/trustedproxy.php (cloudflared's subnet).
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
