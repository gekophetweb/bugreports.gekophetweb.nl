<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Auth\SignInGate;
use App\Support\Auth\SsoProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SsoController
{
    public function redirect(string $provider): Response
    {
        $this->ensureAvailable($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->ensureAvailable($provider);

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            report($e);

            return $this->fail('Signing in with '.SsoProviders::PROVIDERS[$provider]['label'].' did not work. Please try again.');
        }

        $email = mb_strtolower(trim((string) $social->getEmail()));
        if ($email === '' || ! $this->emailIsVerified($provider, $social)) {
            return $this->fail('Your '.SsoProviders::PROVIDERS[$provider]['label'].' account has no verified email address.');
        }

        $user = DB::transaction(function () use ($provider, $social, $email) {
            $account = SocialAccount::query()->where(['provider' => $provider, 'provider_id' => (string) $social->getId()])->first();
            if ($account) {
                return $account->user;
            }

            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => $social->getName() ?: $email,
                'email_verified_at' => now(),
            ]);
            SocialAccount::create(['user_id' => $user->id, 'provider' => $provider, 'provider_id' => (string) $social->getId()]);

            return $user;
        });

        Auth::login($user, remember: true);
        session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function ensureAvailable(string $provider): void
    {
        abort_unless(SignInGate::open() && array_key_exists($provider, SsoProviders::enabled()), 404);
        SsoProviders::apply();
    }

    /** Google says whether the address is verified; GitHub's Socialite driver only returns verified emails. */
    private function emailIsVerified(string $provider, SocialiteUser $social): bool
    {
        return match ($provider) {
            'google' => (bool) ($social->getRaw()['email_verified'] ?? false),
            default => true,
        };
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
