<?php

namespace App\Sources\Nvd;

use App\Models\Language;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Sources\Contracts\IssueSource;
use App\Sources\IssueUpserter;
use App\Support\Nvd\NvdApiKey;
use App\Support\Sleeper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * NVD CVE API 2.0, per CPE of a runtime or server (nginx, Apache httpd, PHP, …), by last-modified range.
 * NVD allows at most 120 days per query and 5 requests per 30 s without an API key (50 with one).
 */
class NvdSource implements IssueSource
{
    public const URL = 'https://services.nvd.nist.gov/rest/json/cves/2.0';

    public const MAX_WINDOW_DAYS = 120;

    public const PAGE_SIZE = 2000;

    public function __construct(private NvdMapper $mapper, private IssueUpserter $upserter, private Sleeper $sleeper) {}

    public function name(): string
    {
        return 'nvd';
    }

    public function feeds(Language $language): array
    {
        return array_values($language->nvd_cpes ?? []);
    }

    public function sync(Language $language, string $feed, SyncRun $run): void
    {
        $end = CarbonImmutable::now()->utc();
        $start = SyncCursor::get('nvd', $feed)?->utc() ?? $end->subDays(config('issues.backfill_days'));

        for ($from = $start; $from->lessThan($end); $from = $to) {
            $to = $from->addDays(self::MAX_WINDOW_DAYS)->min($end);

            for ($index = 0, $total = 1; $index < $total;) {
                $this->waitForTurn();
                $page = $this->fetch($feed, $from, $to, $index);

                foreach ($page['vulnerabilities'] ?? [] as $item) {
                    $run->record($this->upserter->upsert($this->mapper->map($item['cve'], $language->id, $feed), $run));
                }
                $run->save();

                $total = (int) ($page['totalResults'] ?? 0);
                $index += max(1, (int) ($page['resultsPerPage'] ?? self::PAGE_SIZE));
            }
        }

        SyncCursor::put('nvd', $feed, $end);
    }

    /**
     * Spaces NVD requests 6 s apart (0.6 s with an API key) across all jobs and workers, through the
     * shared cache: every CPE is its own job and they run back to back.
     */
    private function waitForTurn(): void
    {
        $interval = NvdApiKey::get() !== null ? 0.6 : 6.0;

        Cache::lock('nvd:throttle', 30)->block(30, function () use ($interval) {
            $now = microtime(true);
            $next = (float) Cache::get('nvd:next-request-at', 0);
            if ($next > $now) {
                $this->sleeper->sleep(round($next - $now, 3));
            }
            Cache::put('nvd:next-request-at', max($now, $next) + $interval, 300);
        });
    }

    private function fetch(string $cpe, CarbonImmutable $from, CarbonImmutable $to, int $startIndex): array
    {
        $request = Http::timeout(60)->acceptJson();
        if ($key = NvdApiKey::get()) {
            $request = $request->withHeaders(['apiKey' => $key]);
        }

        $response = $request->get(self::URL, [
            'virtualMatchString' => $cpe,
            'lastModStartDate' => $from->format('Y-m-d\TH:i:s.v\Z'),
            'lastModEndDate' => $to->format('Y-m-d\TH:i:s.v\Z'),
            'resultsPerPage' => self::PAGE_SIZE,
            'startIndex' => $startIndex,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("NVD request for $cpe failed (HTTP {$response->status()}).");
        }

        return $response->json() ?? [];
    }
}
