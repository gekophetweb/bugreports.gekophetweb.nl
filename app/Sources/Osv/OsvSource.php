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
        [$ids, $newest] = $this->changedSince($feed, $since);

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

    /** @return array{list<string>, ?CarbonImmutable} ids modified after $since, and the newest timestamp seen */
    private function changedSince(string $ecosystem, CarbonImmutable $since): array
    {
        $response = Http::timeout(60)->withOptions(['stream' => true])->get(sprintf(self::CSV_URL, rawurlencode($ecosystem)));
        if (! $response->successful()) {
            throw new RuntimeException("OSV index for $ecosystem could not be fetched (HTTP {$response->status()}).");
        }

        $ids = [];
        $newest = null;
        foreach ($this->lines($response->toPsrResponse()->getBody()) as $line) {
            [$timestamp, $id] = array_pad(explode(',', trim($line), 2), 2, '');
            if ($id === '') {
                continue;
            }
            $modified = CarbonImmutable::parse($timestamp)->utc();
            if ($modified->lessThanOrEqualTo($since)) {
                break; // newest first: everything after this is older
            }
            $newest ??= $modified;
            if (! config('issues.include_malware') && str_starts_with($id, 'MAL-')) {
                continue;
            }
            $ids[$id] = true;
        }

        return [array_keys($ids), $newest];
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
