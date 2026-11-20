<?php

namespace App\Announcements;

use App\Enums\AnnouncementKind;
use App\Models\Announcement;
use App\Models\Language;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

/**
 * The php.net news feed (Atom). Stores the security posts as announcements for the "php" language and
 * hands new ones to the AnnouncementNotifier. Read every 5 minutes with If-None-Match, so an unchanged
 * feed is a 304 without a body.
 */
class PhpNetFeed
{
    public const URL = 'https://www.php.net/feed.atom';

    public const LANGUAGE = 'php';

    private const ETAG_KEY = 'phpnet:feed-etag';

    public function __construct(private AnnouncementNotifier $notifier) {}

    /** @return int the number of new announcements */
    public function sync(): int
    {
        $language = Language::query()->enabled()->where('slug', self::LANGUAGE)->first();
        if (! $language) {
            return 0;
        }

        $request = Http::timeout(30)->accept('application/atom+xml');
        if ($etag = Cache::get(self::ETAG_KEY)) {
            $request = $request->withHeaders(['If-None-Match' => $etag]);
        }
        $response = $request->get(self::URL);
        if ($response->status() === 304) {
            return 0;
        }
        if (! $response->successful()) {
            throw new RuntimeException("The php.net feed could not be fetched (HTTP {$response->status()}).");
        }

        $new = 0;
        foreach (self::parse($response->body()) as $item) {
            $announcement = Announcement::query()->firstOrNew(['external_id' => $item['external_id']]);
            $isNew = ! $announcement->exists;
            $announcement->fill([...$item, 'language_id' => $language->id, 'source' => 'php.net'])->save();
            if ($isNew) {
                $new++;
                $this->notifier->announced($announcement);
            }
        }

        // Only remembered once everything was stored, so a failure is retried with the full feed.
        if ($etag = $response->header('ETag')) {
            Cache::put(self::ETAG_KEY, $etag, now()->addDay());
        }

        return $new;
    }

    /**
     * The security posts in an Atom feed, newest first as php.net lists them.
     *
     * @return list<array{external_id: string, kind: AnnouncementKind, title: string, body: string, url: string,
     *     published_at: CarbonImmutable, scheduled_at: ?CarbonImmutable, scheduled_until: ?CarbonImmutable,
     *     severity: ?string, version: ?string}>
     */
    public static function parse(string $xml): array
    {
        $feed = new SimpleXMLElement($xml, LIBXML_NONET);
        $items = [];

        foreach ($feed->entry as $entry) {
            $title = trim((string) $entry->title);
            $body = self::text($entry->content);
            [$kind, $version] = self::classify($title, $body);
            if (! $kind) {
                continue;
            }

            [$from, $until] = $kind === AnnouncementKind::Upcoming ? self::window($body) : [null, null];
            $items[] = [
                'external_id' => trim((string) $entry->id),
                'kind' => $kind,
                'title' => Str::limit($title, 490),
                'body' => $body,
                'url' => self::link($entry) ?? trim((string) $entry->id),
                'published_at' => CarbonImmutable::parse((string) ($entry->published ?: $entry->updated))->utc(),
                'scheduled_at' => $from,
                'scheduled_until' => $until,
                'severity' => in_array($kind, [AnnouncementKind::Upcoming, AnnouncementKind::Advisory], true) ? self::severity($body) : null,
                'version' => $version,
            ];
        }

        return $items;
    }

    /** @return array{?AnnouncementKind, ?string} */
    private static function classify(string $title, string $body): array
    {
        return match (true) {
            (bool) preg_match('/^Upcoming\b.*\bSecurity Release/i', $title) => [AnnouncementKind::Upcoming, null],
            (bool) preg_match('/^Security advisory\b/i', $title) => [AnnouncementKind::Advisory, null],
            (bool) preg_match('/^Informational advisory\b/i', $title) => [AnnouncementKind::Informational, null],
            preg_match('/^PHP (\d+\.\d+\.\d+) Released/i', $title, $m) && stripos($body, 'security release') !== false => [AnnouncementKind::Release, $m[1]],
            default => [null, null],
        };
    }

    /**
     * "scheduled for Thursday, 19 November 2026, between 14:00 and 14:30 UTC" or
     * "scheduled for Wednesday, 21 October 2026 at 14:00 UTC". Without an end time the window is 30 minutes.
     *
     * @return array{?CarbonImmutable, ?CarbonImmutable}
     */
    private static function window(string $body): array
    {
        $pattern = '/scheduled\s+for\s+(?:[A-Za-z]+,?\s+)?(\d{1,2}\s+[A-Za-z]+\s+\d{4})[,\s]+(?:at|between)\s+(\d{1,2}:\d{2})(?:\s*(?:and|-|–)\s*(\d{1,2}:\d{2}))?\s*UTC/i';
        if (! preg_match($pattern, $body, $m)) {
            return [null, null];
        }

        try {
            $from = CarbonImmutable::parse("{$m[1]} {$m[2]}", 'UTC');
            $until = ! empty($m[3]) ? CarbonImmutable::parse("{$m[1]} {$m[3]}", 'UTC') : $from->addMinutes(30);
        } catch (\Throwable) {
            return [null, null];
        }

        return [$from, $until->greaterThan($from) ? $until : $from->addMinutes(30)];
    }

    private static function severity(string $body): ?string
    {
        if (preg_match('/highest severity is\s+(critical|high|medium|low)/i', $body, $m)
            || preg_match('/\b(critical|high)(?:-severity)?\s+vulnerabilit/i', $body, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    /** The XHTML content as plain text, one paragraph per line. */
    private static function text(SimpleXMLElement $content): string
    {
        // The whole element, XHTML children included (they are in another namespace). Sentences wrap
        // over several lines in the source, so whitespace is collapsed before paragraphs are marked.
        $html = preg_replace('/\s+/u', ' ', (string) $content->asXML());
        $html = preg_replace('~</(p|div|li|h\d)>|<br\s*/?>~i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $lines = array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== '');

        return implode("\n", $lines);
    }

    private static function link(SimpleXMLElement $entry): ?string
    {
        foreach ($entry->link as $link) {
            if ((string) $link['rel'] === 'alternate') {
                return (string) $link['href'];
            }
        }

        return null;
    }
}
