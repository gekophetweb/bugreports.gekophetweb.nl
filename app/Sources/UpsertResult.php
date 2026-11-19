<?php

namespace App\Sources;

use App\Models\Issue;

final readonly class UpsertResult
{
    /** @param  list<string>  $changes  meaningful fields that changed (empty when created) */
    public function __construct(public Issue $issue, public bool $created, public array $changes) {}
}
