<?php

namespace App\Support\Issues;

use Illuminate\Support\Facades\Cookie;

/**
 * Packages someone does not want to see: exact names ("silverstripe/framework") or a whole vendor
 * ("silverstripe/*"), lowercase. An issue is hidden only when it has packages and every one of them is
 * hidden, so an advisory that also affects a package they do use stays visible. Issues without
 * packages (runtime and server CVEs) are never hidden.
 *
 * Signed-in users keep the list on their account (it also filters their emails); visitors keep it in
 * a cookie, which moves into the account when they sign in.
 */
class HiddenPackages
{
    public const MAX = 200;

    /** Visitors keep their hidden packages in this cookie: a JSON list, encrypted like every cookie. */
    public const COOKIE = 'hidden_packages';

    /** Visitors keep the packages they use in this cookie (same format). */
    public const USED_COOKIE = 'used_packages';

    private const COOKIE_MINUTES = 60 * 24 * 365;

    public static function normalize(string $pattern): string
    {
        return mb_strtolower(trim($pattern));
    }

    /** A package name, optionally ending in "/*"; no spaces and no other wildcards. */
    public static function isValid(string $pattern): bool
    {
        return mb_strlen($pattern) <= 200
            && (bool) preg_match('~^[a-z0-9@._\-]+(/[a-z0-9@._\-]+)*(/\*)?$~', $pattern);
    }

    /**
     * @param  array<mixed>  $patterns
     * @return list<string> normalized, valid and unique, at most MAX
     */
    public static function clean(array $patterns): array
    {
        $clean = array_map(fn ($p) => self::normalize($p), array_filter($patterns, 'is_string'));

        return array_slice(array_values(array_unique(array_filter($clean, fn ($p) => self::isValid($p)))), 0, self::MAX);
    }

    /** @return list<string> the valid patterns in the visitor cookie; anything else in it is ignored */
    public static function fromCookie(string $cookie = self::COOKIE): array
    {
        $list = json_decode((string) request()->cookie($cookie, '[]'), true);

        return is_array($list) && array_is_list($list) ? self::clean($list) : [];
    }

    /** @param  list<string>  $hidden */
    public static function queueCookie(array $hidden, string $cookie = self::COOKIE): void
    {
        Cookie::queue($cookie, json_encode(self::clean($hidden)), self::COOKIE_MINUTES);
    }

    public static function forgetCookie(string $cookie = self::COOKIE): void
    {
        Cookie::queue(Cookie::forget($cookie));
    }

    /**
     * @param  list<string>  $hidden
     * @param  list<string>  $packages
     */
    public static function hides(array $hidden, array $packages): bool
    {
        if ($hidden === [] || $packages === []) {
            return false;
        }

        foreach ($packages as $package) {
            if (! self::matchesAny($hidden, mb_strtolower((string) $package))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether any package of an issue is one someone uses (the opposite rule of hides()).
     *
     * @param  list<string>  $used
     * @param  list<string>  $packages
     */
    public static function uses(array $used, array $packages): bool
    {
        foreach ($packages as $package) {
            if (self::matchesAny($used, mb_strtolower((string) $package))) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $hidden */
    private static function matchesAny(array $hidden, string $package): bool
    {
        foreach ($hidden as $pattern) {
            if (str_ends_with($pattern, '/*')
                ? str_starts_with($package, substr($pattern, 0, -1))
                : $package === $pattern) {
                return true;
            }
        }

        return false;
    }
}
