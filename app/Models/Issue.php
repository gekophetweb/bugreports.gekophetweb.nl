<?php

namespace App\Models;

use App\Enums\Severity;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A security issue from OSV or NVD. The source stays the authority; source_url links to it. */
class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    protected $fillable = [
        'source', 'external_id', 'aliases', 'title', 'description', 'severity', 'cvss_score', 'cvss_vector',
        'packages', 'references', 'source_url', 'published_at', 'modified_at', 'withdrawn_at', 'first_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'packages' => 'array',
            'references' => 'array',
            'severity' => Severity::class,
            'cvss_score' => 'float',
            'published_at' => 'datetime',
            'modified_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'first_seen_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class);
    }

    public static function sourceUrlFor(string $source, string $id): string
    {
        return match (true) {
            $source === 'nvd' => 'https://nvd.nist.gov/vuln/detail/'.rawurlencode($id),
            str_starts_with($id, 'GHSA-') => 'https://github.com/advisories/'.rawurlencode($id),
            default => 'https://osv.dev/vulnerability/'.rawurlencode($id),
        };
    }

    public function scopeMinSeverity(Builder $query, Severity $min): Builder
    {
        return $query->whereIn('severity', array_map(fn (Severity $s) => $s->value, $min->andAbove()));
    }

    /** @param  list<string>  $slugs */
    public function scopeForLanguages(Builder $query, array $slugs): Builder
    {
        return $query->whereHas('languages', fn (Builder $q) => $q->whereIn('slug', $slugs));
    }

    public function scopeWithAlias(Builder $query, string $alias): Builder
    {
        return $query->whereJsonContains('aliases', $alias);
    }

    /** New enough to announce: first seen by us, published in the last few days, not withdrawn. */
    public function scopeAnnounceable(Builder $query): Builder
    {
        return $query->whereNotNull('first_seen_at')
            ->where('published_at', '>=', now()->subDays(config('issues.announce_days')))
            ->whereNull('withdrawn_at');
    }

    /** Recent enough that a change to it, while Critical, is emailed to every subscriber of its languages. */
    public function isCriticalUpdateRelevant(Severity $previous): bool
    {
        return ($this->severity === Severity::Critical || $previous === Severity::Critical)
            && $this->published_at !== null
            && $this->published_at->greaterThanOrEqualTo(now()->subDays(config('issues.critical_update_window_days')));
    }

    public function scopeNotWithdrawn(Builder $query): Builder
    {
        return $query->whereNull('withdrawn_at');
    }
}
