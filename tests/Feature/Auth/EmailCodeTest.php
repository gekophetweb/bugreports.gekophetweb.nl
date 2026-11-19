<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    User::factory()->create(['role' => Role::Superadmin]); // site is set up
    Notification::fake();
});

function requestCode(string $email = 'new@example.test'): string
{
    Livewire::test(Login::class)->set('email', $email)->call('requestCode')->assertSet('step', 'code');

    $code = null;
    Notification::assertSentOnDemand(LoginCodeNotification::class, function ($n, $channels, $notifiable) use ($email, &$code) {
        $code = $n->code;

        return $notifiable->routes['mail'] === strtolower($email);
    });

    return $code;
}

it('shows the sign-in page', function () {
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('emails a 6-digit code', function () {
    expect(requestCode())->toMatch('/^\d{6}$/');
});

it('signs in with the right code and creates the user', function () {
    $code = requestCode('New@Example.test');

    Livewire::test(Login::class)
        ->set('email', 'new@example.test')->set('step', 'code')->set('code', $code)
        ->call('verifyCode')
        ->assertRedirect(route('home'));

    $user = User::where('email', 'new@example.test')->sole();
    expect($user->role)->toBe(Role::User)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(auth()->id())->toBe($user->id);
});

it('signs in an existing user without creating another', function () {
    $existing = User::factory()->create(['email' => 'me@example.test']);
    $code = requestCode('me@example.test');

    Livewire::test(Login::class)->set('email', 'me@example.test')->set('step', 'code')->set('code', $code)->call('verifyCode');

    expect(auth()->id())->toBe($existing->id)->and(User::count())->toBe(2);
});

it('rejects an expired code', function () {
    $code = requestCode();
    $this->travel(11)->minutes();

    expect(LoginCode::verify('new@example.test', $code))->toBeFalse();
});

it('rejects a wrong code with an error', function () {
    requestCode();

    Livewire::test(Login::class)->set('email', 'new@example.test')->set('step', 'code')->set('code', '000000')
        ->call('verifyCode')->assertHasErrors('code');
    expect(auth()->check())->toBeFalse();
});

it('locks a code after 5 wrong attempts', function () {
    $code = requestCode();
    $wrong = $code === '111111' ? '222222' : '111111';
    foreach (range(1, 5) as $_) {
        LoginCode::verify('new@example.test', $wrong);
    }

    expect(LoginCode::verify('new@example.test', $code))->toBeFalse();
});

it('limits codes to 5 per hour per email', function () {
    foreach (range(1, 5) as $_) {
        Livewire::test(Login::class)->set('email', 'new@example.test')->call('requestCode')->assertHasNoErrors();
    }

    Livewire::test(Login::class)->set('email', 'NEW@example.test')->call('requestCode')->assertHasErrors('email');
    Notification::assertSentOnDemandTimes(LoginCodeNotification::class, 5);
});

it('does not reuse a consumed code', function () {
    $code = requestCode();

    expect(LoginCode::verify('new@example.test', $code))->toBeTrue()
        ->and(LoginCode::verify('new@example.test', $code))->toBeFalse();
});

it('only accepts the newest code', function () {
    $old = requestCode();
    $this->travel(1)->seconds();
    Livewire::test(Login::class)->set('email', 'new@example.test')->call('requestCode');

    expect(LoginCode::verify('new@example.test', $old))->toBeFalse();
});

it('stays closed until the superadmin exists', function () {
    User::query()->delete();

    Livewire::test(Login::class)->set('email', 'new@example.test')->call('requestCode')->assertHasErrors('email');
    Notification::assertNothingSent();
});

it('signs out', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('home'));

    expect(auth()->check())->toBeFalse();
});
