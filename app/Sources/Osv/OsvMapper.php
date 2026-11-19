<?php

namespace App\Sources\Osv;

use App\Enums\Severity;
use App\Models\Issue;
use App\Sources\IssueData;
use App\Support\Cvss\Cvss31;
use App\Support\SafeUrls;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Maps an OSV record (https://ossf.github.io/osv-schema/) to IssueData. */
class OsvMapper
{
    public function map(array $v, int $languageId): IssueData
    {
        [$severity, $score, $vector] = $this->severity($v);
        $id = (string) $v['id'];

        return new IssueData(
            source: 'osv',
            externalId: $id,
            aliases: array_values(array_unique(array_map('strval', $v['aliases'] ?? []))),
            title: $this->title($v),
            description: filled($v['details'] ?? null) ? (string) $v['details'] : null,
            severity: $severity,
            cvssScore: $score,
            cvssVector: $vector,
            packages: array_values(array_unique(array_filter(array_map(
                fn ($a) => $a['package']['name'] ?? null, $v['affected'] ?? []
            )))),
            references: SafeUrls::filter(array_map(fn ($r) => $r['url'] ?? null, $v['references'] ?? [])),
            sourceUrl: Issue::sourceUrlFor('osv', $id),
            publishedAt: self::date($v['published'] ?? null) ?? self::date($v['modified'] ?? null),
            modifiedAt: self::date($v['modified'] ?? null),
            withdrawnAt: self::date($v['withdrawn'] ?? null),
            languageId: $languageId,
        );
    }

    /** @return array{Severity, ?float, ?string} */
    private function severity(array $v): array
    {
        $entries = collect($v['severity'] ?? []);
        $v3 = $entries->first(fn ($s) => ($s['type'] ?? null) === 'CVSS_V3');
        $label = Severity::fromLabel($v['database_specific']['severity'] ?? null);

        if ($v3 && ($score = Cvss31::score((string) $v3['score'])) !== null) {
            return [Severity::fromScore($score), $score, (string) $v3['score']];
        }

        $other = $entries->first(fn ($s) => in_array($s['type'] ?? null, ['CVSS_V4', 'CVSS_V2'], true));

        return [$label, null, $other['score'] ?? null];
    }

    private function title(array $v): string
    {
        if (filled($v['summary'] ?? null)) {
            return Str::limit(trim((string) $v['summary']), 160);
        }
        $firstLine = trim(strtok((string) ($v['details'] ?? ''), "\n") ?: '');

        return $firstLine !== '' ? Str::limit($firstLine, 160) : (string) $v['id'];
    }

    public static function date(?string $value): ?CarbonImmutable
    {
        return filled($value) ? CarbonImmutable::parse($value)->utc() : null;
    }
}
