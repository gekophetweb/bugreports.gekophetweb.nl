<?php

namespace App\Support\Auth;

use App\Support\Settings;

/**
 * SSO providers through Laravel Socialite. The superadmin enters the client id and secret in
 * Admin → Sign-in. A provider shows on the login page only when it is enabled and has both.
 * Google is enabled by default; GitHub has to be switched on.
 */
class SsoProviders
{
    public const PROVIDERS = [
        'google' => ['label' => 'Google', 'enabled_by_default' => true],
        'github' => ['label' => 'GitHub', 'enabled_by_default' => false],
    ];

    /** @return array<string, string> slug => label */
    public static function enabled(): array
    {
        $enabled = [];
        foreach (self::PROVIDERS as $slug => $provider) {
            if (self::isEnabled($slug) && self::hasCredentials($slug)) {
                $enabled[$slug] = $provider['label'];
            }
        }

        return $enabled;
    }

    public static function isEnabled(string $slug): bool
    {
        $default = self::PROVIDERS[$slug]['enabled_by_default'] ?? false;

        return (bool) Settings::get("sso.$slug.enabled", $default ? '1' : '0');
    }

    public static function hasCredentials(string $slug): bool
    {
        return filled(Settings::get("sso.$slug.client_id")) && filled(Settings::get("sso.$slug.client_secret"));
    }

    /** Copies the stored credentials into config('services.*') for Socialite. */
    public static function apply(): void
    {
        foreach (array_keys(self::PROVIDERS) as $slug) {
            config([
                "services.$slug.client_id" => Settings::get("sso.$slug.client_id"),
                "services.$slug.client_secret" => Settings::get("sso.$slug.client_secret"),
                "services.$slug.redirect" => route('sso.callback', $slug),
            ]);
        }
    }
}
