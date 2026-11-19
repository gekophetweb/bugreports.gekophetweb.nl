<?php

namespace App\Support\Auth;

use App\Models\User;

/**
 * Sign-in stays closed until the superadmin has been created through the setup link,
 * so nobody can claim an account on a fresh install.
 */
class SignInGate
{
    public static function open(): bool
    {
        return User::superadminExists();
    }

    /**
     * SSO providers to show on the login page (slug => label).
     *
     * @return array<string, string>
     */
    public static function ssoProviders(): array
    {
        return class_exists(SsoProviders::class) ? SsoProviders::enabled() : [];
    }
}
