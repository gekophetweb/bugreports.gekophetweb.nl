<?php

namespace App\Models;

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** A user's subscription to one language: new issues at or above min_severity, at the chosen frequency. */
class Subscription extends Model
{
    protected $fillable = ['user_id', 'language_id', 'min_severity', 'frequency', 'all_packages'];

    protected function casts(): array
    {
        return ['min_severity' => Severity::class, 'frequency' => SubscriptionFrequency::class, 'all_packages' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function covers(Issue $issue): bool
    {
        return $issue->severity->rank() >= $this->min_severity->rank();
    }

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('unsubscribe.subscription', ['subscription' => $this->id]);
    }
}
