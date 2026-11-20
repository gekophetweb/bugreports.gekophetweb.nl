<?php

namespace App\Sources\Ghsa;

use App\Enums\Severity;
use App\Sources\IssueData;
use App\Support\Cvss\Cvss31;
use App\Support\SafeUrls;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Maps one GitHub repository security advisory (GET /repos/{owner}/{repo}/security-advisories) to IssueData.
 * php-src publishes these when a release goes out, days before NVD has the CVE.
 */
class GhsaMapper
{
    public function map(array $a, int $languageId, string $repo): IssueData
    {
        $id = (string) $a['ghsa_id'];
        [$severity, $score, $vector] = $this->severity($a);
        $aliases = collect($a['identifiers'] ?? [])->pluck('value')->push($a['cve_id'] ?? null)
            ->filter(fn ($v) => filled($v) && $v !== $id)->map(fn ($v) => (string) $v)->unique()->values()->all();

        return new IssueData(
            source: 'ghsa',
            externalId: $id,
            aliases: $aliases,
            title: Str::limit(trim((string) ($a['summary'] ?? $id)), 160),
            description: filled($a['description'] ?? null) ? (string) $a['description'] : null,
            severity: $severity,
            cvssScore: $score,
            cvssVector: $vector,
            packages: [Str::afterLast($repo, '/')],
            references: SafeUrls::filter([$a['html_url'] ?? null]),
            sourceUrl: (string) ($a['html_url'] ?? "https://github.com/$repo/security/advisories/$id"),
            publishedAt: self::date($a['published_at'] ?? null),
            modifiedAt: self::date($a['updated_at'] ?? null),
            withdrawnAt: self::date($a['withdrawn_at'] ?? null),
            languageId: $languageId,
            scope: 'core',
        );
    }

    /**
     * A CVSS 3.x vector is scored by our own calculator. Otherwise GitHub's rating is used, with the
     * score GitHub gives for CVSS 4.0 (we store it, we do not compute v4 ourselves).
     *
     * @return array{Severity, ?float, ?string}
     */
    private function severity(array $a): array
    {
        $v3 = $a['cvss_severities']['cvss_v3']['vector_string'] ?? $a['cvss']['vector_string'] ?? null;
        if ($v3 && ($score = Cvss31::score($v3)) !== null) {
            return [Severity::fromScore($score), $score, $v3];
        }

        $v4 = $a['cvss_severities']['cvss_v4'] ?? [];
        $score = isset($v4['score']) ? round((float) $v4['score'], 1) : null;

        return [Severity::fromLabel($a['severity'] ?? null), $score, $v4['vector_string'] ?? null];
    }

    public static function date(?string $value): ?CarbonImmutable
    {
        return filled($value) ? CarbonImmutable::parse($value)->utc() : null;
    }
}
