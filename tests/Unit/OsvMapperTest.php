<?php

use App\Enums\Severity;
use App\Sources\Osv\OsvMapper;

function osvFixture(string $id): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/osv/$id.json")), true);
}

it('maps a GHSA record with a CVSS 3.1 vector', function () {
    $data = (new OsvMapper)->map(osvFixture('GHSA-82w8-65qw-gch6'), languageId: 7);

    expect($data->source)->toBe('osv')
        ->and($data->externalId)->toBe('GHSA-82w8-65qw-gch6')
        ->and($data->title)->toStartWith('ALTCHA PHP: verifySolution bypasses')
        ->and($data->severity)->toBe(Severity::High)
        ->and($data->cvssScore)->toBe(7.5)
        ->and($data->cvssVector)->toBe('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:H/A:N')
        ->and($data->aliases)->toBe(['CVE-2026-71481'])
        ->and($data->packages)->toBe(['altcha-org/altcha'])
        ->and($data->references)->toContain('https://github.com/altcha-org/altcha-lib-php/releases/tag/v2.0.3')
        ->and($data->sourceUrl)->toBe('https://github.com/advisories/GHSA-82w8-65qw-gch6')
        ->and($data->publishedAt->toIso8601ZuluString())->toBe('2026-11-18T20:48:18Z')
        ->and($data->withdrawnAt)->toBeNull()
        ->and($data->languageId)->toBe(7);
});

it('falls back to the GHSA label for a v4-only record and uses the first line of details as title', function () {
    $data = (new OsvMapper)->map(osvFixture('GHSA-v4v4-v4v4-v4v4'), languageId: 1);

    expect($data->severity)->toBe(Severity::Medium)
        ->and($data->cvssScore)->toBeNull()
        ->and($data->cvssVector)->toStartWith('CVSS:4.0/')
        ->and($data->title)->toBe('Example library allows cross-site scripting through the title field.')
        ->and($data->packages)->toBe(['example/lib', 'example/lib-extra']);
});

it('marks withdrawn records', function () {
    $record = osvFixture('GHSA-v4v4-v4v4-v4v4') + ['withdrawn' => '2026-11-12T00:00:00Z'];

    expect((new OsvMapper)->map($record, 1)->withdrawnAt?->toIso8601ZuluString())->toBe('2026-11-12T00:00:00Z');
});

it('is Unknown with neither a vector nor a label', function () {
    $record = ['id' => 'OSV-2026-1', 'details' => 'x', 'modified' => '2026-11-01T00:00:00Z'];

    $data = (new OsvMapper)->map($record, 1);

    expect($data->severity)->toBe(Severity::Unknown)->and($data->sourceUrl)->toBe('https://osv.dev/vulnerability/OSV-2026-1');
});
