<?php

namespace App\Livewire\Auth;

use App\Models\LoginCode;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use App\Support\Auth\SignInGate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Sign in with an emailed one-time code (step "email", then step "code"), or with SSO (see SsoController).
 * An unknown email creates a normal user on first sign-in.
 */
#[Layout('layouts.app')]
#[Title('Sign in')]
class Login extends Component
{
    public const CODES_PER_HOUR = 5;

    public string $step = 'email';

    public string $email = '';

    public string $code = '';

    public function requestCode(): void
    {
        $this->validate(['email' => 'required|email:rfc|max:255']);
        $email = mb_strtolower(trim($this->email));

        if (! SignInGate::open()) {
            $this->addError('email', 'This site is not set up yet.');

            return;
        }

        $allowed = RateLimiter::attempt('login-codes:'.$email, self::CODES_PER_HOUR, fn () => true, 3600);
        if (! $allowed) {
            $this->addError('email', 'Too many codes requested for this address. Try again in an hour.');

            return;
        }

        Notification::route('mail', $email)->notify(new LoginCodeNotification(LoginCode::issue($email)));

        $this->email = $email;
        $this->code = '';
        $this->step = 'code';
    }

    public function verifyCode()
    {
        $this->validate(['email' => 'required|email', 'code' => 'required|digits:6']);

        if (! SignInGate::open() || ! LoginCode::verify($this->email, $this->code)) {
            $this->addError('code', 'That code is wrong or has expired. Request a new one if needed.');

            return null;
        }

        $user = User::query()->firstOrCreate(
            ['email' => mb_strtolower(trim($this->email))],
            ['name' => Str::of($this->email)->before('@')->headline()->toString()],
        );
        $user->email_verified_at ??= now();
        $user->save();

        Auth::login($user, remember: true);
        session()->regenerate();

        return $this->redirectIntended(route('home'));
    }

    public function back(): void
    {
        $this->step = 'email';
        $this->code = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.auth.login', ['providers' => SignInGate::open() ? SignInGate::ssoProviders() : []]);
    }
}
