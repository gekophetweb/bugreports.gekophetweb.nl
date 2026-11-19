<?php

namespace App\Support\Digest;

/**
 * What an email lists. watched: updates to watched (or Critical) issues, never shortened.
 * full: up to 20 new issues in full. brief: up to 100 more as one line. overflowCount: the rest.
 */
final readonly class Digest
{
    /**
     * @param  list<array{issue: \App\Models\Issue, fields: list<string>, previous: \App\Enums\Severity, tag: string, unsubscribeUrl?: string}>  $watched
     * @param  list<array{issue: \App\Models\Issue, alerted: bool}>  $full
     * @param  list<array{issue: \App\Models\Issue, alerted: bool}>  $brief
     */
    public function __construct(
        public array $watched,
        public array $full,
        public array $brief,
        public int $overflowCount,
        public string $moreUrl,
    ) {}

    public function isEmpty(): bool
    {
        return $this->watched === [] && $this->full === [];
    }

    public function newCount(): int
    {
        return count($this->full) + count($this->brief) + $this->overflowCount;
    }
}
