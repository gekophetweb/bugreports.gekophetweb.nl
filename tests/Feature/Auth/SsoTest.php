<?php

use App\Enums\Role;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Auth\SsoProviders;
use App\Support\Settings;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    User::factory()->create(['role' => Role::Superadmin]);
});

function configureGoogle(): void
{
    Settings::set('sso.google.client_id', 'gid');
    Settings::set('sso.google.client_secret', 'gsecret', secret: true);
}

function fakeGoogleUser(string $email = 'new@example.test', bool $verified = true, string $id = 'g-1'): void
{
    $user = (new SocialiteUser)->setRaw(['email_verified' => $verified])->map([
        'id' => $id, 'name' => 'New Person', 'email' => $email,
    ]);
    $provider = Mockery::mock(Provider::class);
    $provider->allows('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('hides google until credentials are set', function () {
    expect(SsoProviders::enabled())->toBe([]);
    $this->get('/login')->assertDontSee('Continue with Google');

    configureGoogle();

    expect(SsoProviders::enabled())->toBe(['google' => 'Google']);
    $this->get('/login')->assertSee('Continue with Google');
});

it('keeps github off until it is enabled', function () {
    Settings::set('sso.github.client_id', 'id');
    Settings::set('sso.github.client_secret', 'secret', secret: true);
    expect(SsoProviders::enabled())->not->toHaveKey('github');

    Settings::set('sso.github.enabled', '1');
    expect(SsoProviders::enabled())->toHaveKey('github');
});

it('applies credentials and the callback url to the services config', function () {
    configureGoogle();
    SsoProviders::apply();

    expect(config('services.google.client_id'))->toBe('gid')
        ->and(config('services.google.client_secret'))->toBe('gsecret')
        ->and(config('services.google.redirect'))->toBe(route('sso.callback', 'google'));
});

it('redirects to google', function () {
    configureGoogle();

    $this->get('/auth/google/redirect')->assertRedirectContains('accounts.google.com');
});

it('signs in a new google user', function () {
    configureGoogle();
    fakeGoogleUser();

    $this->get('/auth/google/callback')->assertRedirect(route('home'));

    $user = User::where('email', 'new@example.test')->sole();
    expect(auth()->id())->toBe($user->id)
        ->and($user->role)->toBe(Role::User)
        ->and(SocialAccount::where(['provider' => 'google', 'provider_id' => 'g-1', 'user_id' => $user->id])->exists())->toBeTrue();
});

it('links google to an existing email-code user', function () {
    configureGoogle();
    $existing = User::factory()->create(['email' => 'me@example.test']);
    fakeGoogleUser('ME@example.test');

    $this->get('/auth/google/callback');

    expect(auth()->id())->toBe($existing->id)->and(User::count())->toBe(2);
});

it('finds the user by the linked account even after an email change', function () {
    configureGoogle();
    $existing = User::factory()->create(['email' => 'old@example.test']);
    SocialAccount::create(['user_id' => $existing->id, 'provider' => 'google', 'provider_id' => 'g-1']);
    fakeGoogleUser('renamed@example.test');

    $this->get('/auth/google/callback');

    expect(auth()->id())->toBe($existing->id);
});

it('refuses an unverified google email', function () {
    configureGoogle();
    fakeGoogleUser(verified: false);

    $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse()->and(User::count())->toBe(1);
});

it('404s for a disabled or unknown provider', function () {
    $this->get('/auth/google/redirect')->assertNotFound();
    $this->get('/auth/facebook/redirect')->assertNotFound();
    $this->get('/auth/google/callback')->assertNotFound();
});

it('stays closed until the superadmin exists', function () {
    configureGoogle();
    User::query()->delete();

    $this->get('/auth/google/redirect')->assertNotFound();
});
