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

            if (! $issue) {
                // Another record for the same vulnerability is already stored (PYSEC + GHSA, GO + GHSA, the
                // CVE from NVD): its id is one of our aliases, or it lists our id or one of our aliases.
                $issue = $this->sameVulnerability($data);
                if ($issue) {
                    // An advisory takes over a row that only came from NVD; otherwise merge into the stored row.
                    $mode = $data->source !== 'nvd' && $issue->source === 'nvd' ? 'advisory-takes-over-nvd' : 'merge';
                }
            }

            if (! $issue) {
                $issue = Issue::query()->create([...$this->attributes($data), 'first_seen_at' => now()]);
                $issue->languages()->syncWithoutDetaching([$data->languageId]);

                return new UpsertResult($issue, true, []);
            }

            $previousSeverity = $issue->severity;
            $issue->fill(match ($mode) {
                // Our own record: take everything, but a record without any severity keeps the stored one
                // (an NVD score merged in earlier), and ids merged in earlier stay aliases.
                'same' => [...$this->attributes($data), ...$this->keepScore($issue, $data), 'aliases' => $this->aliases($issue, $data)],
                'merge' => [...$this->scoreIfMissing($issue, $data), 'aliases' => $this->aliases($issue, $data)],
                'advisory-takes-over-nvd' => [
                    ...$this->attributes($data),
                    ...$this->keepScore($issue, $data),
                    'aliases' => $this->aliases($issue, $data, previousId: $issue->external_id),
                ],
            });
            // Once a core source (NVD, the project itself) reported it, it is about the language or server.
            if ($issue->getOriginal('scope') === 'core' || $data->scope === 'core') {
                $issue->scope = 'core';
            }
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

    private function sameVulnerability(IssueData $data): ?Issue
    {
        $ids = array_values(array_unique([$data->externalId, ...$data->aliases]));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return Issue::query()
            ->where(fn ($q) => $q->whereIn('external_id', $data->aliases)
                ->orWhereRaw("jsonb_exists_any(aliases, ARRAY[$placeholders]::text[])", $ids))
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    /** A record without any severity does not wipe the one already stored. */
    private function keepScore(Issue $issue, IssueData $d): array
    {
        return $d->cvssScore === null && $d->severity === Severity::Unknown
            ? ['severity' => $issue->severity, 'cvss_score' => $issue->cvss_score, 'cvss_vector' => $issue->cvss_vector]
            : [];
    }

    /** @return list<string> stored aliases plus the record's id and aliases, without the row's own id */
    private function aliases(Issue $issue, IssueData $d, ?string $previousId = null): array
    {
        $own = $previousId !== null ? $d->externalId : $issue->external_id;
        $all = [...($issue->aliases ?? []), ...$d->aliases, $d->externalId, ...($previousId ? [$previousId] : [])];

        return array_values(array_unique(array_filter($all, fn ($id) => $id !== $own)));
    }

    /** A merged record only fills in the score of the stored issue when that has none. */
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
            'scope' => $d->scope,
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
