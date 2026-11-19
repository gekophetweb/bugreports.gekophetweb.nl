<?php

use App\Enums\LanguageKind;
use App\Enums\Severity;
use App\Models\Issue;
use App\Models\Language;
use Database\Seeders\LanguageSeeder;

it('seeds languages and servers', function () {
    $this->seed(LanguageSeeder::class);

    expect(Language::where('slug', 'nginx')->sole()->kind)->toBe(LanguageKind::Server)
        ->and(Language::where('slug', 'apache')->sole()->nvd_cpes)->toBe(['cpe:2.3:a:apache:http_server'])
        ->and(Language::where('slug', 'php')->sole()->osv_ecosystems)->toBe(['Packagist'])
        ->and(Language::where('slug', 'php')->sole()->kind)->toBe(LanguageKind::Language)
        ->and(Language::count())->toBe(12);
});

it('does not overwrite admin edits when seeding again', function () {
    $this->seed(LanguageSeeder::class);
    Language::where('slug', 'php')->update(['enabled' => false]);

    $this->seed(LanguageSeeder::class);

    expect(Language::where('slug', 'php')->sole()->enabled)->toBeFalse()->and(Language::count())->toBe(12);
});

it('builds source urls', function () {
    expect(Issue::sourceUrlFor('osv', 'GHSA-abcd-efgh-ijkl'))->toBe('https://github.com/advisories/GHSA-abcd-efgh-ijkl')
        ->and(Issue::sourceUrlFor('osv', 'PYSEC-2024-1'))->toBe('https://osv.dev/vulnerability/PYSEC-2024-1')
        ->and(Issue::sourceUrlFor('nvd', 'CVE-2024-1234'))->toBe('https://nvd.nist.gov/vuln/detail/CVE-2024-1234');
});

it('filters by minimum severity and language', function () {
    $php = Language::factory()->create(['slug' => 'php']);
    $go = Language::factory()->create(['slug' => 'go']);
    $critical = Issue::factory()->create(['severity' => Severity::Critical]);
    $critical->languages()->attach($php);
    $low = Issue::factory()->create(['severity' => Severity::Low]);
    $low->languages()->attach($php);
    Issue::factory()->create(['severity' => Severity::High])->languages()->attach($go);

    expect(Issue::minSeverity(Severity::High)->forLanguages(['php'])->pluck('id')->all())->toBe([$critical->id]);
});

it('finds issues by alias', function () {
    $issue = Issue::factory()->create(['aliases' => ['CVE-2024-1', 'GHSA-x']]);
    Issue::factory()->create(['aliases' => ['CVE-2024-2']]);

    expect(Issue::withAlias('CVE-2024-1')->pluck('id')->all())->toBe([$issue->id]);
});
