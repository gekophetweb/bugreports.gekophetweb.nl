<?php

namespace App\Sources\Contracts;

use App\Models\Language;
use App\Models\SyncRun;

interface IssueSource
{
    /** Short name stored on runs and cursors: "osv" or "nvd". */
    public function name(): string;

    /** @return list<string> the feeds to sync for this language (OSV ecosystems, NVD CPEs) */
    public function feeds(Language $language): array;

    /**
     * Imports what changed in one feed since its cursor, recording each upsert on the run.
     * Advances the cursor only when the whole feed succeeded; throws on any failure.
     */
    public function sync(Language $language, string $feed, SyncRun $run): void;
}
