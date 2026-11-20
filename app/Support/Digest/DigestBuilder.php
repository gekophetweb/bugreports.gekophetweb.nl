<?php

namespace App\Support\Digest;

use App\Enums\Severity;
use App\Models\Issue;
use Illuminate\Support\Collection;

/** Ranks and shortens the issues for an email so a busy week stays readable. */
class DigestBuilder
{
    public const FULL = 20;

    public const BRIEF = 100;

    /**
     * @param  Collection<int, array>  $watchedUpdates  items for Digest::$watched, shown in the order given
     * @param  Collection<int, Issue>  $newIssues
     * @param  array<string, mixed>  $filtersForLink  query for the "and N more" link to My issues
     * @param  bool  $markCriticalAlerted  digests: Critical issues were already emailed straight away
     * @param  list<int>  $alertedIds  digests: issues another subscription already emailed straight away
     */
    public static function build(Collection $watchedUpdates, Collection $newIssues, array $filtersForLink, bool $markCriticalAlerted = false, array $alertedIds = []): Digest
    {
        $ranked = $newIssues
            ->sort(fn (Issue $a, Issue $b) => [$b->severity->rank(), $b->published_at?->getTimestamp() ?? 0, $b->id]
                <=> [$a->severity->rank(), $a->published_at?->getTimestamp() ?? 0, $a->id])
            ->values()
            ->map(fn (Issue $issue) => [
                'issue' => $issue,
                'alerted' => ($markCriticalAlerted && $issue->severity === Severity::Critical) || in_array($issue->id, $alertedIds, true),
            ]);

        return new Digest(
            watched: $watchedUpdates->values()->all(),
            full: $ranked->take(self::FULL)->all(),
            brief: $ranked->slice(self::FULL, self::BRIEF)->values()->all(),
            overflowCount: max(0, $ranked->count() - self::FULL - self::BRIEF),
            moreUrl: route('my.issues', $filtersForLink),
        );
    }
}
