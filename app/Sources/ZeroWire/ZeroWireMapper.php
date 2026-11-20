<?php

namespace App\Sources\ZeroWire;

use App\Enums\Severity;
use App\Models\Issue;
use App\Sources\IssueData;
use App\Support\SafeUrls;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Maps one Zero Wire briefing (a JSON Feed item) to IssueData.
 *
 * A briefing often covers several CVEs at once, so its CVEs are not stored as aliases: that would merge
 * the briefing into the first matching advisory, and later advisories of the other CVEs into that one.
 * They are listed in the description instead, and linked through the references.
 *
 * Briefings carry no score. The severity is the highest of the CVEs we already have, else High:
 * Zero Wire only writes about flaws worth acting on, and the next sync raises it once a CVE comes in.
 */
class ZeroWireMapper
{
    public function map(array $item, int $languageId): IssueData
    {
        $meta = is_array($item['_zero_wire'] ?? null) ? $item['_zero_wire'] : [];
        $url = (string) $item['url'];
        $slug = (string) ($meta['slug'] ?? Str::afterLast(rtrim($url, '/'), '/'));
        $cves = collect($meta['cves'] ?? [])->filter(fn ($c) => is_string($c) && preg_match('/^CVE-\d{4}-\d+$/', $c))->unique()->values()->all();
        $tags = collect($item['tags'] ?? [])->filter(fn ($t) => is_string($t))->values()->all();

        return new IssueData(
            source: 'zerowire',
            externalId: 'ZW-'.Str::limit($slug, 200, ''),
            aliases: [],
            title: Str::limit(trim((string) ($item['title'] ?? $slug)), 160),
            description: $this->description($item, $meta, $cves),
            severity: $this->severity($cves),
            cvssScore: null,
            cvssVector: null,
            packages: [],
            references: SafeUrls::filter([
                $url,
                ...array_map(fn ($cve) => 'https://nvd.nist.gov/vuln/detail/'.$cve, $cves),
                ...collect($meta['sources'] ?? [])->flatMap(fn ($s) => is_array($s) ? ($s['links'] ?? []) : [])->pluck('url')->all(),
            ]),
            sourceUrl: $url,
            publishedAt: self::date($item['date_published'] ?? null),
            modifiedAt: self::date($item['date_modified'] ?? null),
            withdrawnAt: null,
            languageId: $languageId,
            // A briefing about an application built on the language (WordPress) is not about the language itself.
            scope: collect($tags)->contains(fn ($t) => strcasecmp($t, 'CMS') === 0) ? 'package' : 'core',
        );
    }

    /** @param  list<string>  $cves */
    private function description(array $item, array $meta, array $cves): ?string
    {
        $parts = array_filter([
            $cves === [] ? null : 'CVEs: '.implode(', ', $cves),
            trim((string) ($item['content_text'] ?? $meta['summary'] ?? $item['summary'] ?? '')),
        ]);

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /** @param  list<string>  $cves */
    private function severity(array $cves): Severity
    {
        if ($cves === []) {
            return Severity::High;
        }
        $placeholders = implode(',', array_fill(0, count($cves), '?'));
        $known = Issue::query()->where('source', '!=', 'zerowire')
            ->where(fn ($q) => $q->whereIn('external_id', $cves)->orWhereRaw("jsonb_exists_any(aliases, ARRAY[$placeholders]::text[])", $cves))
            ->pluck('severity');

        return $known->sortByDesc(fn (Severity $s) => $s->rank())->first(fn (Severity $s) => $s !== Severity::Unknown) ?? Severity::High;
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->utc() : null;
    }
}
