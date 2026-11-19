<?php

namespace App\Http\Middleware;

use App\Support\Mail\MailConfigurator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Octane gives every request a copy of the config from when the worker booted, so mail settings
 * saved in Admin → Mail since then are applied again here (they are cached, so this is cheap).
 */
class ApplyRuntimeSettings
{
    public function __construct(private MailConfigurator $mail) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->mail->apply();

        return $next($request);
    }
}
