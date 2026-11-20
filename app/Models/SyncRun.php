<?php

namespace App\Models;

use App\Sources\SyncDispatcher;
use App\Sources\UpsertResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One sync of one feed. Shown in Admin → Sync; read by NotifySubscribers for new and changed issues. */
class SyncRun extends Model
{
    protected $fillable = [
        'source', 'feed', 'language_id', 'status', 'created_count', 'updated_count', 'error',
        'new_issue_ids', 'changed_issues', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'new_issue_ids' => 'array',
            'changed_issues' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * When each source last finished a run without errors (null: never), for the "checked … ago" line.
     *
     * @return array<string, ?CarbonImmutable> source => finished_at
     */
    public static function lastSuccessful(): array
    {
        $times = static::query()->where('status', 'ok')->whereNotNull('finished_at')
            ->groupBy('source')->selectRaw('source, max(finished_at) as finished_at')->pluck('finished_at', 'source');

        return collect(array_keys(SyncDispatcher::SOURCES))
            ->mapWithKeys(fn (string $source) => [$source => isset($times[$source]) ? CarbonImmutable::parse($times[$source], 'UTC') : null])
            ->all();
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** Adds one upsert's outcome to this run's counters (saved by the caller). */
    public function record(UpsertResult $result): void
    {
        if ($result->created) {
            $this->created_count++;
            $this->new_issue_ids = [...$this->new_issue_ids ?? [], $result->issue->id];
        } elseif ($result->changes !== []) {
            $this->updated_count++;
            $changed = $this->changed_issues ?? [];
            $changed[(string) $result->issue->id] = array_values(array_unique([...$changed[(string) $result->issue->id] ?? [], ...$result->changes]));
            $this->changed_issues = $changed;
        }
    }
}
