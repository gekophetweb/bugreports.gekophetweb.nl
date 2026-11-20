<?php

namespace App\Models;

use App\Enums\AnnouncementKind;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * A php.net post about security: an upcoming release (with its window), an advisory, or a security
 * release. Upcoming releases drive the heads-up email, the banner and the "it's out" email.
 */
class Announcement extends Model
{
    /** Advisories published this close to an upcoming release's window belong to that release. */
    public const RELEASE_MATCH_HOURS = 12;

    protected $fillable = [
        'language_id', 'source', 'external_id', 'kind', 'title', 'body', 'url', 'published_at',
        'scheduled_at', 'scheduled_until', 'severity', 'version', 'notified_at', 'release_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => AnnouncementKind::class,
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'scheduled_until' => 'datetime',
            'notified_at' => 'datetime',
            'release_notified_at' => 'datetime',
        ];
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** When the release window ends (or starts, when php.net gave only one time). */
    public function windowEnd(): ?CarbonInterface
    {
        return $this->scheduled_until ?? $this->scheduled_at;
    }

    public function isOut(): bool
    {
        return $this->release_notified_at !== null || ($this->windowEnd()?->isPast() ?? false);
    }

    /** "Thu 19 Nov, 14:00 UTC" */
    public function windowLabel(): ?string
    {
        return $this->scheduled_at?->copy()->utc()->format('D j M, H:i').' UTC';
    }

    /** "15:00–15:30 Europe/Amsterdam" */
    public function localWindowLabel(): ?string
    {
        if (! $this->scheduled_at) {
            return null;
        }
        $from = $this->scheduled_at->inDisplayTz();
        $until = $this->scheduled_until?->inDisplayTz();

        return $from->format('D j M, H:i').($until ? '–'.$until->format('H:i') : '').' '.config('app.display_timezone');
    }

    /**
     * The fixed versions php.net released for this upcoming release, newest first.
     *
     * @return list<string>
     */
    public function releaseVersions(): array
    {
        return static::query()->where('language_id', $this->language_id)->where('kind', AnnouncementKind::Release)
            ->where('published_at', '>=', $this->published_at->copy()->subHour())
            ->where('published_at', '<=', ($this->windowEnd() ?? $this->published_at)->copy()->addDays(2))
            ->pluck('version')->filter()->unique()
            ->sort(fn ($a, $b) => version_compare($b, $a))->values()->all();
    }

    /** The project's own advisories published with this release, most severe first. */
    public function releaseIssues(): Collection
    {
        if (! $this->scheduled_at) {
            return collect();
        }

        return Issue::query()->where('source', 'ghsa')->notWithdrawn()
            ->whereHas('languages', fn ($q) => $q->where('languages.id', $this->language_id))
            ->whereBetween('published_at', [
                $this->scheduled_at->copy()->subHours(self::RELEASE_MATCH_HOURS),
                $this->scheduled_at->copy()->addHours(self::RELEASE_MATCH_HOURS),
            ])
            ->get()
            ->sortBy([fn ($a, $b) => $b->severity->rank() <=> $a->severity->rank(), ['published_at', 'asc']])
            ->values();
    }

    /**
     * Ids of these issues that the "it's out" email of a pending upcoming release will cover, so the
     * normal new-issue alert leaves them out for users with advance warnings on.
     *
     * @param  Collection<int, Issue>  $issues  with languages loaded
     * @return list<int>
     */
    public static function heldIssueIds(Collection $issues): array
    {
        $candidates = $issues->filter(fn (Issue $i) => $i->source === 'ghsa' && $i->published_at);
        if ($candidates->isEmpty()) {
            return [];
        }
        $pending = static::query()->where('kind', AnnouncementKind::Upcoming)->whereNull('release_notified_at')
            ->whereNotNull('scheduled_at')->where('scheduled_at', '>=', now()->subDays(3))->get();

        return $candidates->filter(fn (Issue $issue) => $pending->contains(fn (self $a) => $issue->languages->contains('id', $a->language_id)
            && abs($issue->published_at->diffInHours($a->scheduled_at, false)) <= self::RELEASE_MATCH_HOURS))
            ->pluck('id')->values()->all();
    }

    /**
     * For the banner on the issue list: upcoming releases until 3 days after their window, and advisories
     * of the last 7 days, of the languages in view (all when no language filter is set).
     *
     * @param  list<string>  $languageSlugs
     */
    public static function forBanner(array $languageSlugs): Collection
    {
        return static::query()->with('language:id,slug,name')
            ->whereIn('kind', [AnnouncementKind::Upcoming, AnnouncementKind::Advisory, AnnouncementKind::Informational])
            ->when($languageSlugs !== [], fn ($q) => $q->whereHas('language', fn ($l) => $l->whereIn('slug', $languageSlugs)))
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('kind', AnnouncementKind::Upcoming)
                    ->whereRaw('coalesce(scheduled_until, scheduled_at, published_at) > ?', [now()->subDays(3)]))
                ->orWhere(fn ($q) => $q->where('kind', '!=', AnnouncementKind::Upcoming->value)
                    ->where('published_at', '>', now()->subDays(7))))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();
    }
}
