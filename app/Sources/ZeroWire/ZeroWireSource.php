<?php

namespace App\Sources\ZeroWire;

use App\Models\Language;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Sources\Contracts\IssueSource;
use App\Sources\IssueUpserter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cloudflare Radar Zero Wire: hand-written briefings about serious vulnerabilities, often before the
 * CVEs reach NVD. One JSON Feed for everything; a language takes the briefings tagged with one of its
 * zero_wire_tags (e.g. "PHP"). The feed is small (a few dozen briefings), so every run reads it whole.
 */
class ZeroWireSource implements IssueSource
{
    public const URL = 'https://radar.cloudflare.com/zero-wire/feed';

    /** Every language reads the same feed; fetch it once per few minutes. */
    private const CACHE_SECONDS = 240;

    public function __construct(private ZeroWireMapper $mapper, private IssueUpserter $upserter) {}

    public function name(): string
    {
        return 'zerowire';
    }

    public function feeds(Language $language): array
    {
        return ($language->zero_wire_tags ?? []) === [] ? [] : [$language->slug];
    }

    public function sync(Language $language, string $feed, SyncRun $run): void
    {
        $tags = array_map('mb_strtolower', $language->zero_wire_tags ?? []);
        $since = now()->subDays(config('issues.backfill_days'))->toImmutable();
        $newest = SyncCursor::get('zerowire', $feed);

        foreach ($this->items() as $item) {
            $itemTags = array_map(fn ($t) => mb_strtolower((string) $t), $item['tags'] ?? []);
            $modified = ZeroWireMapper::date($item['date_modified'] ?? $item['date_published'] ?? null);
            if (array_intersect($tags, $itemTags) === [] || ! $modified || $modified->lessThan($since) || empty($item['url'])) {
                continue;
            }

            $run->record($this->upserter->upsert($this->mapper->map($item, $language->id), $run));
            if (! $newest || $modified->greaterThan($newest)) {
                $newest = $modified;
            }
        }
        $run->save();

        if ($newest) {
            SyncCursor::put('zerowire', $feed, $newest);
        }
    }

    /** @return list<array<string, mixed>> */
    private function items(): array
    {
        return Cache::remember('zerowire:feed', self::CACHE_SECONDS, function () {
            $response = Http::timeout(30)->accept('application/feed+json')->get(self::URL, ['format' => 'json']);
            $items = $response->successful() ? $response->json('items') : null;
            if (! is_array($items)) {
                throw new RuntimeException("The Zero Wire feed could not be fetched (HTTP {$response->status()}).");
            }

            return array_values(array_filter($items, 'is_array'));
        });
    }
}
