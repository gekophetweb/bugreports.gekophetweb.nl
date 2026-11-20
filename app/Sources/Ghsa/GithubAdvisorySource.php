<?php

namespace App\Sources\Ghsa;

use App\Models\Language;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Sources\Contracts\IssueSource;
use App\Sources\IssueUpserter;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * GitHub repository security advisories (php/php-src), newest update first, back to the cursor.
 * Without a token GitHub allows 60 requests an hour; one repository every 10 minutes uses 6.
 */
class GithubAdvisorySource implements IssueSource
{
    public const URL = 'https://api.github.com/repos/%s/security-advisories';

    /** Pages per run at most: 100 advisories each, so a first import of a busy repository still ends. */
    private const MAX_PAGES = 10;

    public function __construct(private GhsaMapper $mapper, private IssueUpserter $upserter) {}

    public function name(): string
    {
        return 'ghsa';
    }

    public function feeds(Language $language): array
    {
        return array_values($language->github_repos ?? []);
    }

    public function sync(Language $language, string $feed, SyncRun $run): void
    {
        $cursor = SyncCursor::get('ghsa', $feed);
        $since = $cursor
            ? $cursor->subMinutes((int) config('issues.lookback_minutes'))
            : now()->subDays(config('issues.backfill_days'))->toImmutable();
        $newest = $cursor;

        $url = sprintf(self::URL, $feed);
        $query = ['state' => 'published', 'sort' => 'updated', 'direction' => 'desc', 'per_page' => 100];

        for ($page = 0; $url && $page < self::MAX_PAGES; $page++) {
            // Passing a query (even an empty one) replaces the one in the URL, so the next page's link is used as is.
            $response = $query === [] ? $this->request()->get($url) : $this->request()->get($url, $query);
            if (! $response->successful()) {
                throw new RuntimeException("GitHub advisories for $feed could not be fetched (HTTP {$response->status()}).");
            }

            foreach ($response->json() ?? [] as $advisory) {
                $updated = GhsaMapper::date($advisory['updated_at'] ?? null);
                if ($updated && $updated->lessThanOrEqualTo($since)) {
                    break 2; // newest first: the rest is older
                }
                $run->record($this->upserter->upsert($this->mapper->map($advisory, $language->id, $feed), $run));
                if ($updated && (! $newest || $updated->greaterThan($newest))) {
                    $newest = $updated;
                }
            }
            $run->save();

            // The next page's URL already carries the query.
            $url = $this->nextPage($response->header('Link'));
            $query = [];
        }

        if ($newest && (! $cursor || $newest->greaterThan($cursor))) {
            SyncCursor::put('ghsa', $feed, $newest);
        }
    }

    private function request()
    {
        $request = Http::timeout(30)->acceptJson()->withHeaders(['X-GitHub-Api-Version' => '2022-11-28']);

        return ($token = config('issues.github_token')) ? $request->withToken($token) : $request;
    }

    private function nextPage(?string $link): ?string
    {
        return $link && preg_match('/<([^>]+)>;\s*rel="next"/', $link, $m) ? $m[1] : null;
    }
}
