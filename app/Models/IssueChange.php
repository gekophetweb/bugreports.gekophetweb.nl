<?php

namespace App\Models;

use App\Enums\Severity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A meaningful change to an issue (see IssueUpserter::MEANINGFUL), read for watcher emails and digests. */
class IssueChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['issue_id', 'sync_run_id', 'fields', 'previous_severity'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'previous_severity' => Severity::class];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
