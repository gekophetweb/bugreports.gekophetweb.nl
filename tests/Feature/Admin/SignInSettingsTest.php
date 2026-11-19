<?php

use App\Enums\Role;
use App\Livewire\Admin\SignIn;
use App\Models\User;
use App\Support\Auth\SsoProviders;
use App\Support\Settings;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => Role::Superadmin]));
});

it('shows the callback urls', function () {
    $this->get(route('admin.sign-in'))->assertOk()->assertSee(route('sso.callback', 'google'));
});

it('saves google credentials and enables google', function () {
    Livewire::test(SignIn::class)
        ->set('providers.google.client_id', 'gid')
        ->set('providers.google.client_secret', 'gsecret')
        ->call('save')->assertHasNoErrors();

    expect(SsoProviders::enabled())->toHaveKey('google')
        ->and(Settings::get('sso.google.client_secret'))->toBe('gsecret');
});

it('keeps the stored secret when left blank and never shows it', function () {
    Livewire::test(SignIn::class)->set('providers.google.client_id', 'gid')->set('providers.google.client_secret', 'gsecret')->call('save');

    Livewire::test(SignIn::class)->assertSet('providers.google.client_secret', '')
        ->set('providers.google.client_id', 'gid2')->call('save');

    expect(Settings::get('sso.google.client_secret'))->toBe('gsecret')->and(Settings::get('sso.google.client_id'))->toBe('gid2');
    $this->get(route('admin.sign-in'))->assertDontSee('gsecret');
});

it('can switch google off and github on', function () {
    Livewire::test(SignIn::class)
        ->set('providers.google.enabled', false)
        ->set('providers.github.enabled', true)->set('providers.github.client_id', 'id')->set('providers.github.client_secret', 's')
        ->call('save');

    expect(SsoProviders::isEnabled('google'))->toBeFalse()->and(SsoProviders::enabled())->toBe(['github' => 'GitHub']);
});
