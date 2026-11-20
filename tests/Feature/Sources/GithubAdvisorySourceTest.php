<?php

use App\Enums\Severity;
use App\Jobs\SyncFeed;
use App\Models\Issue;
use App\Models\Language;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Sources\Ghsa\GithubAdvisorySource;
use App\Sources\SyncDispatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const GHSA_URL = 'api.github.com/repos/php/php-src/security-advisories';

beforeEach(function () {
    $this->travelTo(now()->parse('2026-11-20 12:00:00', 'UTC'));
    $this->php = Language::factory()->create(['slug' => 'php', 'github_repos' => ['php/php-src']]);
});

function ghsaFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/ghsa/php-src.json')), true);
}

function runGhsa(): SyncRun
{
    SyncFeed::dispatchSync(GithubAdvisorySource::class, test()->php->id, 'php/php-src');

    return SyncRun::query()->latest('id')->first();
}

it('lists the GitHub repositories as feeds and is a sync source', function () {
    expect(app(GithubAdvisorySource::class)->feeds($this->php))->toBe(['php/php-src'])
        ->and(SyncDispatcher::SOURCES)->toHaveKey('ghsa');
});

it('imports the repository advisories with their severity, score and CVE', function () {
    Http::fake([GHSA_URL.'*' => Http::response(ghsaFixture())]);

    $run = runGhsa();

    $issue = Issue::where('external_id', 'GHSA-8mjj-2r7p-4hg2')->sole();
    expect($run->status)->toBe('ok')
        ->and($run->created_count)->toBe(2)
        ->and($issue->source)->toBe('ghsa')
        ->and($issue->severity)->toBe(Severity::Critical)
        ->and($issue->cvss_score)->toBe(9.2)
        ->and($issue->cvss_vector)->toStartWith('CVSS:4.0/')
        ->and($issue->aliases)->toBe(['CVE-2026-129474'])
        ->and($issue->packages)->toBe(['php-src'])
        ->and($issue->source_url)->toBe('https://github.com/php/php-src/security/advisories/GHSA-8mjj-2r7p-4hg2')
        ->and($issue->published_at->toIso8601ZuluString())->toBe('2026-11-19T14:06:20Z')
        ->and($issue->languages->pluck('slug')->all())->toBe(['php'])
        ->and(SyncCursor::where(['source' => 'ghsa', 'feed' => 'php/php-src'])->sole()->cursor->toIso8601ZuluString())
        ->toBe('2026-11-19T19:04:50Z');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sort=updated') && str_contains($r->url(), 'direction=desc')
        && str_contains($r->url(), 'state=published'));
});

it('stops at advisories it already has', function () {
    Http::fake([GHSA_URL.'*' => Http::response(ghsaFixture())]);
    runGhsa();

    $run = runGhsa();

    expect($run->created_count)->toBe(0)->and($run->updated_count)->toBe(0)->and(Issue::count())->toBe(2);
});

it('follows the next page link', function () {
    [$first, $second] = ghsaFixture();
    Http::fake(function (Request $request) use ($first, $second) {
        return str_contains($request->url(), 'after=page2')
            ? Http::response([$second])
            : Http::response([$first], 200, ['Link' => '<https://'.GHSA_URL.'?per_page=100&after=page2>; rel="next"']);
    });

    runGhsa();

    expect(Issue::count())->toBe(2);
    Http::assertSentCount(2);
});

it('takes over an issue that so far only came from NVD', function () {
    $nvd = Issue::factory()->create(['source' => 'nvd', 'external_id' => 'CVE-2026-129474', 'aliases' => []]);
    $nvd->languages()->attach($this->php);
    Http::fake([GHSA_URL.'*' => Http::response([ghsaFixture()[0]])]);

    runGhsa();

    $issue = $nvd->fresh();
    expect(Issue::count())->toBe(1)
        ->and($issue->external_id)->toBe('GHSA-8mjj-2r7p-4hg2')
        ->and($issue->source)->toBe('ghsa')
        ->and($issue->aliases)->toContain('CVE-2026-129474');
});

it('sends the GitHub token when one is configured', function () {
    config(['issues.github_token' => 'gh-test-token']);
    Http::fake([GHSA_URL.'*' => Http::response([])]);

    runGhsa();

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer gh-test-token'));
});

it('records a failed run and keeps the cursor when GitHub refuses', function () {
    Http::fake([GHSA_URL.'*' => Http::response(['message' => 'API rate limit exceeded'], 403)]);

    $run = runGhsa();

    expect($run->status)->toBe('failed')->and($run->error)->toContain('403')->and(SyncCursor::count())->toBe(0);
});
