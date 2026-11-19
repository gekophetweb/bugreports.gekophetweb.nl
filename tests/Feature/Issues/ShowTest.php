<?php

use App\Enums\Severity;
use App\Models\Issue;
use App\Models\Language;

it('shows an issue with a link to its source', function () {
    $issue = Issue::factory()->create([
        'external_id' => 'GHSA-82w8-65qw-gch6', 'title' => 'ALTCHA signature bypass', 'description' => 'Full details here.',
        'severity' => Severity::High, 'cvss_score' => 7.5, 'aliases' => ['CVE-2026-71481'], 'packages' => ['altcha-org/altcha'],
        'references' => ['https://github.com/altcha-org/altcha-lib-php/releases/tag/v2.0.3'],
        'source_url' => 'https://github.com/advisories/GHSA-82w8-65qw-gch6',
    ]);
    $issue->languages()->attach(Language::factory()->create(['name' => 'PHP']));

    $this->get('/issues/GHSA-82w8-65qw-gch6')->assertOk()
        ->assertSee('ALTCHA signature bypass')->assertSee('Full details here.')
        ->assertSee('CVE-2026-71481')->assertSee('altcha-org/altcha')->assertSee('PHP')
        ->assertSee('href="https://github.com/advisories/GHSA-82w8-65qw-gch6"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('7.5');
});

it('still shows a withdrawn issue, marked as withdrawn', function () {
    Issue::factory()->create(['external_id' => 'CVE-2026-70002', 'withdrawn_at' => now()]);

    $this->get('/issues/CVE-2026-70002')->assertOk()->assertSee('Withdrawn');
});

it('404s for an unknown issue', function () {
    $this->get('/issues/CVE-0000-0000')->assertNotFound();
});

it('escapes descriptions from the sources', function () {
    Issue::factory()->create(['external_id' => 'X-1', 'description' => '<script>alert(1)</script>']);

    $this->get('/issues/X-1')->assertDontSee('<script>alert(1)</script>', false);
});
