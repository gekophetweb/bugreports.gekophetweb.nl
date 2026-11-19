<?php

use App\Support\Settings;
use Illuminate\Support\Facades\DB;

it('stores and reads plain values', function () {
    Settings::set('mail.host', 'smtp.example.test');

    expect(Settings::get('mail.host'))->toBe('smtp.example.test')
        ->and(Settings::get('missing', 'fallback'))->toBe('fallback');
});

it('encrypts secrets at rest', function () {
    Settings::set('mail.password', 'hunter2', secret: true);

    expect(DB::table('settings')->where('key', 'mail.password')->value('value'))->not->toBe('hunter2')
        ->and(Settings::get('mail.password'))->toBe('hunter2');
});

it('sees changes made after a cached read', function () {
    Settings::set('mail.host', 'a');
    Settings::get('mail.host');
    Settings::set('mail.host', 'b');

    expect(Settings::get('mail.host'))->toBe('b');
});

it('reads several keys at once', function () {
    Settings::set('a', '1');
    Settings::set('b', '2');

    expect(Settings::many(['a', 'b', 'c']))->toBe(['a' => '1', 'b' => '2', 'c' => null]);
});

it('removes a key when set to null', function () {
    Settings::set('a', '1');
    Settings::set('a', null);

    expect(DB::table('settings')->where('key', 'a')->exists())->toBeFalse();
});
