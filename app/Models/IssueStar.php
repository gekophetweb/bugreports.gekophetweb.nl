<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A starred issue: shown first in every list that user sees. Sends no email (that is IssueWatch). */
class IssueStar extends Model
{
    protected $fillable = ['user_id', 'issue_id'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
