<?php

namespace App\Sources;

use App\Enums\Severity;
use App\Models\Issue;
use App\Models\IssueChange;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;

/**
 * Stores an IssueData: creates or updates the issue, attaches the language, and records which
 * meaningful fields changed. A bare "modified" timestamp is not a meaningful change.
 */
class IssueUpserter
{
    /** Fields whose change notifies watchers (and language subscribers for Critical issues). */
    public const MEANINGFUL = ['severity', 'cvss_score', 'description', 'references', 'withdrawn_at'];

    public function upsert(IssueData $data, ?SyncRun $run = null): UpsertResult
    {
        return DB::transaction(function () use ($data, $run) {
            $issue = Issue::query()->where('external_id', $data->externalId)->lockForUpdate()->first();
            $mode = 'same';

            if (! $issue && $data->source === 'nvd') {
                // An OSV advisory already lists this CVE as an alias: enrich that issue instead.
                $issue = Issue::query()->withAlias($data->externalId)->lockForUpdate()->first();
                $mode = 'nvd-into-osv';
            }
            if (! $issue && $data->source === 'osv' && $data->aliases !== []) {
                // The CVE came in from NVD first: the advisory takes over that row.
                $issue = Issue::query()->where('source', 'nvd')->whereIn('external_id', $data->aliases)->lockForUpdate()->first();
                $mode = 'osv-takes-over-nvd';
            }

            if (! $issue) {
                $issue = Issue::query()->create([...$this->attributes($data), 'first_seen_at' => now()]);
                $issue->languages()->syncWithoutDetaching([$data->languageId]);

                return new UpsertResult($issue, true, []);
            }

            $previousSeverity = $issue->severity;
            $issue->fill(match ($mode) {
                'same' => $this->attributes($data),
                'nvd-into-osv' => $this->scoreIfMissing($issue, $data),
                'osv-takes-over-nvd' => [
                    ...$this->attributes($data),
                    ...($data->cvssScore === null && $data->severity === Severity::Unknown ? [
                        'severity' => $issue->severity, 'cvss_score' => $issue->cvss_score, 'cvss_vector' => $issue->cvss_vector,
                    ] : []),
                    'aliases' => array_values(array_unique([...$data->aliases, $issue->external_id])),
                ],
            });
            $changes = $this->meaningfulChanges($issue);
            $issue->save();
            $issue->languages()->syncWithoutDetaching([$data->languageId]);

            if ($changes !== []) {
                IssueChange::query()->create([
                    'issue_id' => $issue->id,
                    'sync_run_id' => $run?->id,
                    'fields' => $changes,
                    'previous_severity' => $previousSeverity,
                ]);
            }

            return new UpsertResult($issue, false, $changes);
        });
    }

    /** An NVD CVE only fills in the score of the OSV issue that aliases it when that has none. */
    private function scoreIfMissing(Issue $issue, IssueData $d): array
    {
        if ($issue->cvss_score !== null || $d->cvssScore === null && $d->severity === Severity::Unknown) {
            return [];
        }

        return ['severity' => $d->severity, 'cvss_score' => $d->cvssScore, 'cvss_vector' => $d->cvssVector];
    }

    /** @return array<string, mixed> */
    private function attributes(IssueData $d): array
    {
        return [
            'source' => $d->source,
            'external_id' => $d->externalId,
            'aliases' => $d->aliases,
            'title' => mb_substr($d->title, 0, 500),
            'description' => $d->description,
            'severity' => $d->severity,
            'cvss_score' => $d->cvssScore,
            'cvss_vector' => $d->cvssVector,
            'packages' => $d->packages,
            'references' => $d->references,
            'source_url' => $d->sourceUrl,
            'published_at' => $d->publishedAt,
            'modified_at' => $d->modifiedAt,
            'withdrawn_at' => $d->withdrawnAt,
        ];
    }

    /** @return list<string> */
    private function meaningfulChanges(Issue $issue): array
    {
        return array_values(array_filter(self::MEANINGFUL, function (string $field) use ($issue) {
            if (! $issue->isDirty($field)) {
                return false;
            }
            // Eloquent compares JSON and decimals as strings; compare the decoded values instead.
            $before = $issue->getOriginal($field);
            $after = $issue->getAttribute($field);

            return match ($field) {
                'cvss_score' => ($before === null ? null : round((float) $before, 1)) !== ($after === null ? null : round((float) $after, 1)),
                'withdrawn_at' => $before?->toIso8601String() !== $after?->toIso8601String(),
                default => $before != $after,
            };
        }));
    }
}
