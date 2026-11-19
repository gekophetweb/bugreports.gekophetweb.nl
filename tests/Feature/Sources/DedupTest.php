<?php

use App\Enums\Severity;
use App\Models\Issue;
use App\Models\Language;
use App\Sources\IssueData;
use App\Sources\IssueUpserter;
use Carbon\CarbonImmutable;

function issueData(array $overrides = []): IssueData
{
    $d = array_merge([
        'source' => 'osv', 'externalId' => 'GHSA-aaaa-bbbb-cccc', 'aliases' => ['CVE-2026-70001'],
        'title' => 'OSV title', 'description' => 'OSV details', 'severity' => Severity::Unknown, 'cvssScore' => null,
        'cvssVector' => null, 'packages' => ['vendor/pkg'], 'references' => [], 'sourceUrl' => 'https://github.com/advisories/GHSA-aaaa-bbbb-cccc',
        'publishedAt' => CarbonImmutable::parse('2026-11-10'), 'modifiedAt' => CarbonImmutable::parse('2026-11-10'),
        'withdrawnAt' => null, 'languageId' => 0,
    ], $overrides);

    return new IssueData(...$d);
}

function nvdData(int $languageId, array $overrides = []): IssueData
{
    return issueData(array_merge([
        'source' => 'nvd', 'externalId' => 'CVE-2026-70001', 'aliases' => [], 'title' => 'NVD title',
        'description' => 'NVD description', 'severity' => Severity::Critical, 'cvssScore' => 9.8,
        'cvssVector' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 'packages' => ['nginx'],
        'sourceUrl' => 'https://nvd.nist.gov/vuln/detail/CVE-2026-70001', 'languageId' => $languageId,
    ], $overrides));
}

beforeEach(function () {
    $this->php = Language::factory()->create(['slug' => 'php']);
    $this->nginx = Language::factory()->create(['slug' => 'nginx']);
    $this->upserter = app(IssueUpserter::class);
});

it('merges an NVD CVE into the OSV issue that aliases it', function () {
    $this->upserter->upsert(issueData(['languageId' => $this->php->id]));

    $result = $this->upserter->upsert(nvdData($this->nginx->id));

    $issue = Issue::sole();
    expect($result->created)->toBeFalse()
        ->and($issue->external_id)->toBe('GHSA-aaaa-bbbb-cccc')
        ->and($issue->title)->toBe('OSV title')
        ->and($issue->languages->pluck('slug')->sort()->values()->all())->toBe(['nginx', 'php'])
        ->and($issue->severity)->toBe(Severity::Critical)
        ->and($issue->cvss_score)->toBe(9.8)
        ->and($result->changes)->toBe(['severity', 'cvss_score']);
});

it('keeps the OSV score when the NVD CVE merges into an already-scored issue', function () {
    $this->upserter->upsert(issueData(['languageId' => $this->php->id, 'severity' => Severity::High, 'cvssScore' => 7.5]));

    $result = $this->upserter->upsert(nvdData($this->nginx->id));

    expect(Issue::sole()->cvss_score)->toBe(7.5)->and($result->changes)->toBe([]);
});

it('lets a later OSV advisory take over the NVD row for its CVE', function () {
    $this->upserter->upsert(nvdData($this->nginx->id));

    $result = $this->upserter->upsert(issueData(['languageId' => $this->php->id]));

    $issue = Issue::sole();
    expect($result->created)->toBeFalse()
        ->and($issue->external_id)->toBe('GHSA-aaaa-bbbb-cccc')
        ->and($issue->source)->toBe('osv')
        ->and($issue->aliases)->toContain('CVE-2026-70001')
        ->and($issue->severity)->toBe(Severity::Critical)
        ->and($issue->languages->pluck('slug')->sort()->values()->all())->toBe(['nginx', 'php']);
});

it('keeps merging NVD updates into the taken-over row', function () {
    $this->upserter->upsert(nvdData($this->nginx->id));
    $this->upserter->upsert(issueData(['languageId' => $this->php->id]));

    $this->upserter->upsert(nvdData($this->nginx->id));

    expect(Issue::count())->toBe(1);
});
