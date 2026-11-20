<?php

namespace App\Support\Issues;

use App\Models\Issue;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which issues someone sees. Packages are hidden by default, so an issue is visible when it is
 * - about the language or server itself (scope "core"), or
 * - about a package they use (any of its packages is on their used list), or
 * - in a language they follow with "All packages" on,
 * and not all of its packages are on their hidden list. Issues a user starred or watched are always visible.
 */
final class PackageVisibility
{
    /**
     * @param  list<string>  $hidden
     * @param  list<string>  $used
     * @param  list<int>  $allPackagesLanguageIds
     */
    public function __construct(
        public readonly array $hidden,
        public readonly array $used,
        public readonly array $allPackagesLanguageIds = [],
        public readonly ?int $userId = null,
    ) {}

    public static function forUser(User $user): self
    {
        return new self(
            $user->hiddenPackages(),
            $user->usedPackages(),
            $user->subscriptions()->where('all_packages', true)->pluck('language_id')->map(fn ($id) => (int) $id)->all(),
            $user->id,
        );
    }

    /**
     * A visitor's lists come from their cookies, or from a change made earlier in this page.
     *
     * @param  list<string>|null  $hidden
     * @param  list<string>|null  $used
     */
    public static function forVisitor(?array $hidden = null, ?array $used = null): self
    {
        return new self(
            $hidden ?? HiddenPackages::fromCookie(),
            $used ?? HiddenPackages::fromCookie(HiddenPackages::USED_COOKIE),
        );
    }

    /** Limits $query to visible issues, or with $onlyHidden to the rest (for the "N hidden" count). */
    public function apply(Builder $query, bool $onlyHidden = false): Builder
    {
        [$sql, $bindings] = $this->visibleSql();

        return $onlyHidden ? $query->whereRaw("not ($sql)", $bindings) : $query->whereRaw("($sql)", $bindings);
    }

    /** For emails: whether a subscription of this user covers this issue's packages. */
    public static function emailable(Issue $issue, User $user, Subscription $subscription): bool
    {
        $packages = $issue->packages ?? [];

        return ! HiddenPackages::hides($user->hiddenPackages(), $packages)
            && ($issue->scope === 'core' || $subscription->all_packages || HiddenPackages::uses($user->usedPackages(), $packages));
    }

    /** @return array{string, list<mixed>} */
    private function visibleSql(): array
    {
        $parts = ["issues.scope = 'core'"];
        $bindings = [];

        if ($this->used !== []) {
            [$match, $matchBindings] = self::matchSql($this->used);
            $parts[] = "exists (select 1 from jsonb_array_elements_text(issues.packages) as p(name) where $match)";
            array_push($bindings, ...$matchBindings);
        }
        if ($this->allPackagesLanguageIds !== []) {
            $parts[] = 'exists (select 1 from issue_language il where il.issue_id = issues.id and il.language_id in ('
                .implode(',', array_fill(0, count($this->allPackagesLanguageIds), '?')).'))';
            array_push($bindings, ...$this->allPackagesLanguageIds);
        }
        $sql = '('.implode(' or ', $parts).')';

        if ($this->hidden !== []) {
            [$match, $matchBindings] = self::matchSql($this->hidden);
            $sql .= ' and not (jsonb_array_length(issues.packages) > 0'
                ." and not exists (select 1 from jsonb_array_elements_text(issues.packages) as p(name) where not ($match)))";
            array_push($bindings, ...$matchBindings);
        }

        if ($this->userId !== null) {
            $sql = "($sql)"
                .' or exists (select 1 from issue_stars s where s.issue_id = issues.id and s.user_id = ?)'
                .' or exists (select 1 from issue_watches w where w.issue_id = issues.id and w.user_id = ?)';
            array_push($bindings, $this->userId, $this->userId);
        }

        return [$sql, $bindings];
    }

    /**
     * "lower(p.name) in (…) or lower(p.name) like …" for exact names and vendor/* patterns.
     *
     * @param  list<string>  $patterns
     * @return array{string, list<string>}
     */
    private static function matchSql(array $patterns): array
    {
        $exact = array_values(array_filter($patterns, fn ($p) => ! str_ends_with($p, '/*')));
        $prefixes = array_values(array_map(
            fn ($p) => addcslashes(substr($p, 0, -1), '%_\\').'%',
            array_filter($patterns, fn ($p) => str_ends_with($p, '/*')),
        ));

        $sql = [];
        if ($exact !== []) {
            $sql[] = 'lower(p.name) in ('.implode(',', array_fill(0, count($exact), '?')).')';
        }
        foreach ($prefixes as $_) {
            $sql[] = 'lower(p.name) like ?';
        }

        return ['('.implode(' or ', $sql).')', [...$exact, ...$prefixes]];
    }
}
