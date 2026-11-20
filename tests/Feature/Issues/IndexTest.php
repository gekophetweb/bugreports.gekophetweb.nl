<?php

use App\Enums\Severity;
use App\Livewire\Issues\Index;
use App\Models\Issue;
use App\Models\Language;
use App\Models\SyncRun;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->parse('2026-11-19 12:00:00', 'UTC'));
    $this->php = Language::factory()->create(['slug' => 'php', 'name' => 'PHP']);
    $this->python = Language::factory()->create(['slug' => 'python', 'name' => 'Python']);
    $this->go = Language::factory()->create(['slug' => 'go', 'name' => 'Go']);
});

function issueIn(Language $language, array $attributes = []): Issue
{
    $issue = Issue::factory()->create($attributes);
    $issue->languages()->attach($language);

    return $issue;
}

function listed(array $params = []): array
{
    return Livewire::withQueryParams($params)->test(Index::class)->viewData('issues')->pluck('external_id')->all();
}

it('shows the issues page', function () {
    issueIn($this->php, ['title' => 'Template injection in twig', 'severity' => Severity::Critical, 'cvss_score' => 9.8]);

    $this->get('/')->assertOk()->assertSee('Template injection in twig')->assertSee('9.8')->assertSee('Critical');
});

it('lists newest first', function () {
    issueIn($this->php, ['external_id' => 'OLD', 'published_at' => now()->subDays(3)]);
    issueIn($this->php, ['external_id' => 'NEW', 'published_at' => now()->subDay()]);

    expect(listed())->toBe(['NEW', 'OLD']);
});

it('filters by one or more languages', function () {
    issueIn($this->php, ['external_id' => 'P']);
    issueIn($this->python, ['external_id' => 'Y']);
    issueIn($this->go, ['external_id' => 'G']);

    expect(listed(['languages' => ['php']]))->toBe(['P'])
        ->and(collect(listed(['languages' => ['php', 'go']]))->sort()->values()->all())->toBe(['G', 'P']);
});

it('filters by minimum severity', function () {
    issueIn($this->php, ['external_id' => 'C', 'severity' => Severity::Critical, 'published_at' => now()->subHours(1)]);
    issueIn($this->php, ['external_id' => 'H', 'severity' => Severity::High, 'published_at' => now()->subHours(2)]);
    issueIn($this->php, ['external_id' => 'M', 'severity' => Severity::Medium, 'published_at' => now()->subHours(3)]);

    expect(listed(['severity' => 'high']))->toBe(['C', 'H']);
});

it('filters by a period preset', function () {
    issueIn($this->php, ['external_id' => 'RECENT', 'published_at' => now()->subDays(6)]);
    issueIn($this->php, ['external_id' => 'OLDER', 'published_at' => now()->subDays(8)]);

    expect(listed(['period' => '7d']))->toBe(['RECENT'])
        ->and(listed(['period' => '24h']))->toBe([]);
});

it('filters by a custom range of Amsterdam days', function () {
    // 1 Nov 00:30 Amsterdam = 31 Oct 23:30 UTC; 10 Nov 23:30 Amsterdam = 10 Nov 22:30 UTC
    issueIn($this->php, ['external_id' => 'FIRST', 'published_at' => now()->parse('2026-10-31 23:30:00', 'UTC')]);
    issueIn($this->php, ['external_id' => 'LAST', 'published_at' => now()->parse('2026-11-10 22:30:00', 'UTC')]);
    issueIn($this->php, ['external_id' => 'AFTER', 'published_at' => now()->parse('2026-11-10 23:30:00', 'UTC')]);
    issueIn($this->php, ['external_id' => 'BEFORE', 'published_at' => now()->parse('2026-10-31 22:30:00', 'UTC')]);

    expect(listed(['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-10']))->toBe(['LAST', 'FIRST']);
});

it('filters on the updated date when asked', function () {
    issueIn($this->php, ['external_id' => 'TOUCHED', 'published_at' => now()->subDays(60), 'modified_at' => now()->subDay()]);
    issueIn($this->php, ['external_id' => 'STALE', 'published_at' => now()->subDays(2), 'modified_at' => now()->subDays(2)]);

    expect(listed(['period' => '24h']))->toBe([])
        ->and(listed(['period' => '7d', 'date_field' => 'modified']))->toBe(['TOUCHED', 'STALE']);
});

it('ignores an invalid date instead of failing', function () {
    issueIn($this->php, ['external_id' => 'X']);

    $this->get('/?period=custom&from=yesterday-ish&to=2026-13-45')->assertOk();
    expect(listed(['period' => 'custom', 'from' => 'nope']))->toBe(['X']);
});

it('finds an issue by its CVE alias, package or title', function () {
    issueIn($this->php, ['external_id' => 'GHSA-aaaa-bbbb-cccc', 'aliases' => ['CVE-2026-71481'], 'packages' => ['altcha-org/altcha'], 'title' => 'Signature bypass']);
    issueIn($this->php, ['external_id' => 'OTHER', 'title' => 'Something else', 'packages' => ['x/y']]);

    expect(listed(['q' => 'cve-2026-71481']))->toBe(['GHSA-aaaa-bbbb-cccc'])
        ->and(listed(['q' => 'altcha']))->toBe(['GHSA-aaaa-bbbb-cccc'])
        ->and(listed(['q' => 'signature']))->toBe(['GHSA-aaaa-bbbb-cccc']);
});

it('hides withdrawn issues', function () {
    issueIn($this->php, ['external_id' => 'GONE', 'withdrawn_at' => now()]);

    expect(listed())->toBe([]);
});

it('paginates by 25', function () {
    foreach (range(1, 30) as $i) {
        issueIn($this->php, ['published_at' => now()->subMinutes($i)]);
    }

    expect(listed())->toHaveCount(25);
});

it('shows when each source was last checked successfully', function () {
    $run = fn (string $source, string $status, int $minutesAgo) => SyncRun::query()->create([
        'source' => $source, 'feed' => 'feed', 'status' => $status,
        'started_at' => now()->subMinutes($minutesAgo + 1), 'finished_at' => now()->subMinutes($minutesAgo),
    ]);
    $run('osv', 'ok', 30);
    $run('osv', 'ok', 2);
    $run('nvd', 'ok', 8);
    $run('nvd', 'failed', 1);   // a failed run does not count as checked
    $run('nvd', 'running', 0);

    $this->get('/')->assertOk()
        ->assertSeeInOrder(['OSV checked', '2 minutes ago'])
        ->assertSeeInOrder(['NVD checked', '8 minutes ago'])
        ->assertDontSee('1 minute ago');
});

it('says when a source has not been checked yet', function () {
    $this->get('/')->assertOk()->assertSee('OSV not checked yet')->assertSee('NVD not checked yet');
});
