<?php

use App\Enums\Role;
use App\Livewire\Setup;
use App\Models\User;
use App\Support\Settings;
use Livewire\Livewire;

function setupToken(): string
{
    test()->artisan('setup:url')->assertSuccessful();

    return Settings::get('setup.token');
}

it('prints a setup link while there is no superadmin', function () {
    $this->artisan('setup:url')->expectsOutputToContain('/setup/')->assertSuccessful();

    expect(Settings::get('setup.token'))->toHaveLength(40);
});

it('keeps the same token across restarts', function () {
    $first = setupToken();

    expect(setupToken())->toBe($first);
});

it('prints nothing once a superadmin exists', function () {
    User::factory()->create(['role' => Role::Superadmin]);

    $this->artisan('setup:url')->doesntExpectOutputToContain('/setup/')->assertSuccessful();
});

it('shows the setup form for the right token', function () {
    $this->get('/setup/'.setupToken())->assertOk()->assertSee('Create the superadmin');
});

it('creates the first user as superadmin and signs them in', function () {
    $token = setupToken();

    Livewire::test(Setup::class, ['token' => $token])
        ->set('name', 'Jaap')
        ->set('email', 'Admin@Example.test')
        ->call('save')
        ->assertRedirect(route('admin.setup'));

    $user = User::sole();
    expect($user->role)->toBe(Role::Superadmin)
        ->and($user->email)->toBe('admin@example.test')
        ->and($user->isSuperadmin())->toBeTrue()
        ->and(auth()->id())->toBe($user->id)
        ->and(Settings::get('setup.token'))->toBeNull();
});

it('rejects a wrong token with 404', function () {
    setupToken();

    $this->get('/setup/'.str_repeat('x', 40))->assertNotFound();
});

it('works only once', function () {
    $token = setupToken();
    Livewire::test(Setup::class, ['token' => $token])->set('name', 'A')->set('email', 'a@example.test')->call('save');
    auth()->logout();

    $this->get('/setup/'.$token)->assertNotFound();
    expect(User::count())->toBe(1);
});

it('refuses a stale form after another tab finished setup', function () {
    $token = setupToken();
    $stale = Livewire::test(Setup::class, ['token' => $token]);
    Livewire::test(Setup::class, ['token' => $token])->set('name', 'A')->set('email', 'a@example.test')->call('save');

    $stale->set('name', 'B')->set('email', 'b@example.test')->call('save')->assertStatus(404);

    expect(User::where('role', Role::Superadmin)->count())->toBe(1);
});

it('validates name and email', function () {
    Livewire::test(Setup::class, ['token' => setupToken()])
        ->set('name', '')->set('email', 'nope')->call('save')
        ->assertHasErrors(['name' => 'required', 'email' => 'email']);
});

it('requires superadmin for admin pages', function () {
    $this->actingAs(User::factory()->create())->get('/admin/mail')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => Role::Superadmin]))->get('/admin/mail')->assertOk();
});
