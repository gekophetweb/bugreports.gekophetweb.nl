<?php

namespace App\Support\Issues;

use Illuminate\Database\Eloquent\Builder;

/**
 * Packages a user does not want to see: exact names ("silverstripe/framework") or a whole vendor
 * ("silverstripe/*"), lowercase. An issue is hidden only when it has packages and every one of them is
 * hidden, so an advisory that also affects a package the user does use stays visible. Issues without
 * packages (runtime and server CVEs) are never hidden.
 */
class HiddenPackages
{
    public const MAX = 200;

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

    /**
     * Limits $query to issues not hidden for this user. Starred and watched issues always stay.
     * With $onlyHidden, it does the opposite (for the "N hidden" count).
     *
     * @param  list<string>  $hidden
     */
    public static function apply(Builder $query, array $hidden, int $userId, bool $onlyHidden = false): Builder
    {
        if ($hidden === []) {
            return $onlyHidden ? $query->whereRaw('false') : $query;
        }

        $exact = array_values(array_filter($hidden, fn ($p) => ! str_ends_with($p, '/*')));
        $prefixes = array_values(array_map(
            fn ($p) => addcslashes(substr($p, 0, -1), '%_\\').'%',
            array_filter($hidden, fn ($p) => str_ends_with($p, '/*')),
        ));

        $matches = [];
        $bindings = [];
        if ($exact !== []) {
            $matches[] = 'lower(p.name) in ('.implode(',', array_fill(0, count($exact), '?')).')';
            array_push($bindings, ...$exact);
        }
        foreach ($prefixes as $prefix) {
            $matches[] = 'lower(p.name) like ?';
            $bindings[] = $prefix;
        }

        // Hidden: it has packages, none of which is outside the hidden list, and the user did not star or watch it.
        $sql = 'jsonb_array_length(issues.packages) > 0'
            .' and not exists (select 1 from jsonb_array_elements_text(issues.packages) as p(name) where not ('.implode(' or ', $matches).'))'
            .' and not exists (select 1 from issue_stars s where s.issue_id = issues.id and s.user_id = ?)'
            .' and not exists (select 1 from issue_watches w where w.issue_id = issues.id and w.user_id = ?)';
        array_push($bindings, $userId, $userId);

        return $onlyHidden ? $query->whereRaw("($sql)", $bindings) : $query->whereRaw("not ($sql)", $bindings);
    }
}
