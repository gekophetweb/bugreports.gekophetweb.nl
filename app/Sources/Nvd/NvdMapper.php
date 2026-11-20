<?php

namespace App\Sources\Nvd;

use App\Enums\Severity;
use App\Models\Issue;
use App\Sources\IssueData;
use App\Support\SafeUrls;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Maps one NVD CVE API 2.0 "cve" object to IssueData. NVD dates carry no zone but are UTC. */
class NvdMapper
{
    public function map(array $cve, int $languageId, string $cpe): IssueData
    {
        $id = (string) $cve['id'];
        $description = collect($cve['descriptions'] ?? [])->firstWhere('lang', 'en')['value'] ?? null;
        [$severity, $score, $vector] = $this->severity($cve['metrics'] ?? []);
        $modified = self::date($cve['lastModified'] ?? null);

        return new IssueData(
            source: 'nvd',
            externalId: $id,
            aliases: [],
            title: $this->title($description, $id),
            description: $description,
            severity: $severity,
            cvssScore: $score,
            cvssVector: $vector,
            packages: array_values(array_filter([self::product($cpe)])),
            references: SafeUrls::filter(array_map(fn ($r) => $r['url'] ?? null, $cve['references'] ?? [])),
            sourceUrl: Issue::sourceUrlFor('nvd', $id),
            publishedAt: self::date($cve['published'] ?? null),
            modifiedAt: $modified,
            withdrawnAt: ($cve['vulnStatus'] ?? null) === 'Rejected' ? ($modified ?? CarbonImmutable::now()) : null,
            languageId: $languageId,
            scope: 'core',
        );
    }

    /** @return array{Severity, ?float, ?string} */
    private function severity(array $metrics): array
    {
        foreach (['cvssMetricV31', 'cvssMetricV30'] as $key) {
            $metric = $this->primary($metrics[$key] ?? []);
            if ($metric && isset($metric['cvssData']['baseScore'])) {
                $score = round((float) $metric['cvssData']['baseScore'], 1);

                return [Severity::fromScore($score), $score, $metric['cvssData']['vectorString'] ?? null];
            }
        }

        // CVSS v4 and v2 are stored as a label only (v4 scoring tables are not implemented).
        if ($metric = $this->primary($metrics['cvssMetricV40'] ?? [])) {
            return [Severity::fromLabel($metric['cvssData']['baseSeverity'] ?? null), null, $metric['cvssData']['vectorString'] ?? null];
        }
        if ($metric = $this->primary($metrics['cvssMetricV2'] ?? [])) {
            return [Severity::fromLabel($metric['baseSeverity'] ?? null), null, null];
        }

        return [Severity::Unknown, null, null];
    }

    /** NVD's own ("Primary") rating first, otherwise the first one listed. */
    private function primary(array $entries): ?array
    {
        return collect($entries)->firstWhere('type', 'Primary') ?? ($entries[0] ?? null);
    }

    private function title(?string $description, string $id): string
    {
        if (blank($description)) {
            return $id;
        }
        $sentence = preg_split('/(?<=[.!?])\s+/', trim($description), 2)[0];

        return Str::limit($sentence, 160);
    }

    /** "cpe:2.3:a:f5:nginx" → "nginx", "cpe:2.3:a:erlang:erlang\/otp" → "erlang/otp". */
    public static function product(string $cpe): ?string
    {
        $parts = preg_split('/(?<!\\\\):/', $cpe);

        return isset($parts[4]) ? str_replace('\\', '', $parts[4]) : null;
    }

    public static function date(?string $value): ?CarbonImmutable
    {
        return filled($value) ? CarbonImmutable::parse($value, 'UTC')->utc() : null;
    }
}
