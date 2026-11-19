<?php

use Illuminate\Support\Facades\Route;

it('reports healthy', function () {
    $this->get('/up')->assertOk();
});

it('builds https urls behind the tunnel', function () {
    Route::get('/_url', fn () => url('/x'));

    $this->get('/_url', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'bugreports.gekophetweb.nl', 'X-Forwarded-Port' => '443'])
        ->assertSee('https://bugreports.gekophetweb.nl/x', false);
});

it('ignores forwarded headers from untrusted addresses', function () {
    Route::get('/_url', fn () => url('/x'));

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->get('/_url', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'])
        ->assertDontSee('evil.example');
});

it('stores in UTC and shows Amsterdam time', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('app.display_timezone'))->toBe('Europe/Amsterdam')
        ->and(now()->parse('2026-07-01 10:00:00', 'UTC')->inDisplayTz()->format('H:i'))->toBe('12:00');
});
