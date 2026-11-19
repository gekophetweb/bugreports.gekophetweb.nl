<?php

namespace App\Sources\Osv;

use App\Models\Language;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Sources\Contracts\IssueSource;
use App\Sources\IssueUpserter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * OSV.dev (includes GitHub Security Advisories). Per ecosystem, reads modified_id.csv
 * ("<RFC3339>,<id>" lines, newest first) up to the cursor, then fetches each changed record.
 */
class OsvSource implements IssueSource
{
    public const CSV_URL = 'https://osv-vulnerabilities.storage.googleapis.com/%s/modified_id.csv';

    public const VULN_URL = 'https://api.osv.dev/v1/vulns/%s';

    private const CONCURRENCY = 10;

    public function __construct(private OsvMapper $mapper, private IssueUpserter $upserter) {}

    public function name(): string
    {
        return 'osv';
    }

    public function feeds(Language $language): array
    {
        return array_values($language->osv_ecosystems ?? []);
    }

    public function sync(Language $language, string $feed, SyncRun $run): void
    {
        $since = SyncCursor::get('osv', $feed) ?? now()->subDays(config('issues.backfill_days'))->toImmutable();
        [$ids, $newest] = $this->batch($this->changedSince($feed, $since));

        foreach (array_chunk($ids, self::CONCURRENCY) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $id) => $pool->as($id)->timeout(30)->acceptJson()->get(sprintf(self::VULN_URL, rawurlencode($id))),
                $chunk,
            ));

            foreach ($chunk as $id) {
                $response = $responses[$id];
                if (! $response instanceof Response || ! $response->successful()) {
                    $status = $response instanceof Response ? 'HTTP '.$response->status() : $response::class;
                    throw new RuntimeException("OSV record $id could not be fetched ($status).");
                }
                $run->record($this->upserter->upsert($this->mapper->map($response->json(), $language->id), $run));
            }
            $run->save();
        }

        if ($newest) {
            SyncCursor::put('osv', $feed, $newest);
        }
    }

    /**
     * The oldest issues.osv_batch changes, so a large first import catches up over several runs.
     * A batch never ends inside a group of records sharing one timestamp, because the cursor is that
     * timestamp and the next run only takes what is newer.
     *
     * @param  list<array{string, CarbonImmutable}>  $changes  newest first
     * @return array{list<string>, ?CarbonImmutable} ids to fetch, and the cursor to store afterwards
     */
    private function batch(array $changes): array
    {
        $changes = array_reverse($changes); // oldest first
        $limit = max(1, (int) config('issues.osv_batch', 1500));

        if (count($changes) > $limit) {
            $cut = $limit;
            $boundary = $changes[$cut][1];
            while ($cut > 0 && $changes[$cut - 1][1]->equalTo($boundary)) {
                $cut--;
            }
            $changes = array_slice($changes, 0, $cut > 0 ? $cut : $this->groupEnd($changes, $limit));
        }

        $ids = array_keys(array_fill_keys(array_column($changes, 0), true));

        return [$ids, $changes === [] ? null : end($changes)[1]];
    }

    /** When the first $limit records all share one timestamp, take that whole group. */
    private function groupEnd(array $changes, int $limit): int
    {
        $end = $limit;
        while ($end < count($changes) && $changes[$end][1]->equalTo($changes[0][1])) {
            $end++;
        }

        return $end;
    }

    /** @return list<array{string, CarbonImmutable}> [id, modified] after $since, newest first */
    private function changedSince(string $ecosystem, CarbonImmutable $since): array
    {
        $response = Http::timeout(60)->withOptions(['stream' => true])->get(sprintf(self::CSV_URL, rawurlencode($ecosystem)));
        if (! $response->successful()) {
            throw new RuntimeException("OSV index for $ecosystem could not be fetched (HTTP {$response->status()}).");
        }

        $changes = [];
        foreach ($this->lines($response->toPsrResponse()->getBody()) as $line) {
            [$timestamp, $id] = array_pad(explode(',', trim($line), 2), 2, '');
            if ($id === '') {
                continue;
            }
            $modified = CarbonImmutable::parse($timestamp)->utc();
            if ($modified->lessThanOrEqualTo($since)) {
                break; // newest first: everything after this is older
            }
            if (! config('issues.include_malware') && str_starts_with($id, 'MAL-')) {
                continue; // skipped before batching, so they do not use up a batch
            }
            $changes[] = [$id, $modified];
        }

        return $changes;
    }

    /** @return \Generator<string> */
    private function lines(StreamInterface $body): \Generator
    {
        $buffer = '';
        while (! $body->eof()) {
            $buffer .= $body->read(8192);
            while (($pos = strpos($buffer, "\n")) !== false) {
                yield substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
            }
        }
        if (trim($buffer) !== '') {
            yield $buffer;
        }
        $body->close();
    }
}
