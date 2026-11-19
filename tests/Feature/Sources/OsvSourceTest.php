<?php

use App\Enums\Severity;
use App\Jobs\SyncFeed;
use App\Models\Issue;
use App\Models\IssueChange;
use App\Models\Language;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Sources\Osv\OsvSource;
use Illuminate\Support\Facades\Http;

const OSV_CSV = 'osv-vulnerabilities.storage.googleapis.com/Packagist/modified_id.csv';

beforeEach(function () {
    $this->travelTo(now()->parse('2026-11-19 12:00:00', 'UTC'));
    $this->php = Language::factory()->create(['slug' => 'php', 'osv_ecosystems' => ['Packagist']]);
    $this->osvFaked = false;
    $this->osvCsv = '';
    $this->osvVulns = [];
});

function osvJson(string $id, ?callable $edit = null): array
{
    $data = json_decode(file_get_contents(base_path("tests/Fixtures/osv/$id.json")), true);

    return $edit ? $edit($data) : $data;
}

/** Sets what the fake OSV serves. One Http::fake callback reads it, so later calls replace earlier ones. */
function fakeOsv(string|int|null $csv = null, array $vulns = []): void
{
    test()->osvCsv = $csv ?? file_get_contents(base_path('tests/Fixtures/osv/Packagist-modified_id.csv'));
    test()->osvVulns = $vulns + [
        'GHSA-82w8-65qw-gch6' => osvJson('GHSA-82w8-65qw-gch6'),
        'GHSA-v4v4-v4v4-v4v4' => osvJson('GHSA-v4v4-v4v4-v4v4'),
        'MAL-2026-1234' => ['id' => 'MAL-2026-1234', 'summary' => 'Malicious package', 'modified' => '2026-11-17T21:15:05Z'],
        'GHSA-old0-old0-old0' => ['id' => 'GHSA-old0-old0-old0', 'summary' => 'Old', 'modified' => '2026-06-01T00:00:00Z'],
    ];

    if (! test()->osvFaked) {
        test()->osvFaked = true;
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, OSV_CSV)) {
                return is_int(test()->osvCsv) ? Http::response('', test()->osvCsv) : Http::response(test()->osvCsv);
            }
            $id = rawurldecode(basename(parse_url($url, PHP_URL_PATH)));
            $body = test()->osvVulns[$id] ?? 404;

            return is_int($body) ? Http::response('', $body) : Http::response($body);
        });
    }
}

function runOsv(): SyncRun
{
    SyncFeed::dispatchSync(OsvSource::class, test()->php->id, 'Packagist');

    return SyncRun::query()->latest('id')->first();
}

it('lists the ecosystems as feeds', function () {
    expect(app(OsvSource::class)->feeds($this->php))->toBe(['Packagist']);
});

it('imports issues modified within the backfill window and links them to the language', function () {
    fakeOsv();

    $run = runOsv();

    expect($run->status)->toBe('ok')
        ->and($run->created_count)->toBe(2)
        ->and(Issue::pluck('external_id')->sort()->values()->all())->toBe(['GHSA-82w8-65qw-gch6', 'GHSA-v4v4-v4v4-v4v4'])
        ->and(Issue::where('external_id', 'GHSA-82w8-65qw-gch6')->sole()->languages->pluck('slug')->all())->toBe(['php'])
        ->and($run->new_issue_ids)->toHaveCount(2)
        ->and(SyncCursor::where(['source' => 'osv', 'feed' => 'Packagist'])->sole()->cursor->toIso8601ZuluString())
        ->toBe('2026-11-18T21:00:39Z');
});

it('creates nothing and fetches no records on an unchanged second run', function () {
    fakeOsv();
    runOsv();

    $run = runOsv();

    expect($run->created_count)->toBe(0)->and($run->updated_count)->toBe(0)->and(Issue::count())->toBe(2);
    Http::assertSentCount(4); // run 1: csv + 2 records; run 2: csv only
});

it('skips MAL ids by default and includes them when configured', function () {
    fakeOsv();
    runOsv();
    expect(Issue::where('external_id', 'MAL-2026-1234')->exists())->toBeFalse();

    SyncCursor::query()->delete();
    config(['issues.include_malware' => true]);
    runOsv();
    expect(Issue::where('external_id', 'MAL-2026-1234')->exists())->toBeTrue();
});

it('respects the backfill window', function () {
    config(['issues.backfill_days' => 400]);
    fakeOsv();

    runOsv();

    expect(Issue::where('external_id', 'GHSA-old0-old0-old0')->exists())->toBeTrue();
});

it('records a failed run and keeps the cursor when OSV returns 500', function () {
    fakeOsv(vulns: ['GHSA-v4v4-v4v4-v4v4' => 500]);

    $run = runOsv();

    expect($run->status)->toBe('failed')
        ->and($run->error)->toContain('500')
        ->and($run->finished_at)->not->toBeNull()
        ->and(SyncCursor::count())->toBe(0);
});

it('records a failed run when the csv is unavailable', function () {
    fakeOsv(503);

    expect(runOsv()->status)->toBe('failed');
});

it('reports changed fields when the severity changes', function () {
    fakeOsv();
    runOsv();

    $csv = "2026-11-19T11:00:00Z,GHSA-82w8-65qw-gch6\n2026-11-18T21:00:39.447883165Z,GHSA-82w8-65qw-gch6\n";
    fakeOsv($csv, ['GHSA-82w8-65qw-gch6' => osvJson('GHSA-82w8-65qw-gch6', function ($d) {
        $d['severity'][0]['score'] = 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H';
        $d['modified'] = '2026-11-19T11:00:00Z';

        return $d;
    })]);
    $run = runOsv();

    $issue = Issue::where('external_id', 'GHSA-82w8-65qw-gch6')->sole();
    $change = IssueChange::sole();
    expect($issue->severity)->toBe(Severity::Critical)
        ->and($run->updated_count)->toBe(1)
        ->and($change->fields)->toBe(['severity', 'cvss_score'])
        ->and($change->previous_severity)->toBe(Severity::High)
        ->and($change->sync_run_id)->toBe($run->id)
        ->and($run->changed_issues)->toBe([(string) $issue->id => ['severity', 'cvss_score']]);
});

it('reports no change when only the modified date differs', function () {
    fakeOsv();
    runOsv();

    fakeOsv("2026-11-19T11:00:00Z,GHSA-82w8-65qw-gch6\n", ['GHSA-82w8-65qw-gch6' => osvJson('GHSA-82w8-65qw-gch6', function ($d) {
        $d['modified'] = '2026-11-19T11:00:00Z';

        return $d;
    })]);
    $run = runOsv();

    expect(IssueChange::count())->toBe(0)->and($run->changed_issues)->toBe([])
        ->and(Issue::where('external_id', 'GHSA-82w8-65qw-gch6')->sole()->modified_at->toIso8601ZuluString())->toBe('2026-11-19T11:00:00Z');
});

it('processes at most one batch per run, oldest first, and resumes', function () {
    config(['issues.osv_batch' => 1]);
    fakeOsv();

    $first = runOsv();
    expect($first->status)->toBe('ok')
        ->and(Issue::pluck('external_id')->all())->toBe(['GHSA-v4v4-v4v4-v4v4'])
        ->and(SyncCursor::sole()->cursor->toIso8601ZuluString())->toBe('2026-11-10T10:00:00Z');

    runOsv();
    expect(Issue::count())->toBe(2)
        ->and(SyncCursor::sole()->cursor->toIso8601ZuluString())->toBe('2026-11-18T21:00:39Z');
});

it('never splits records that share a timestamp across batches', function () {
    config(['issues.osv_batch' => 2]);
    $csv = "2026-11-18T10:00:00Z,GHSA-82w8-65qw-gch6\n2026-11-18T10:00:00Z,GHSA-v4v4-v4v4-v4v4\n2026-11-17T10:00:00Z,GHSA-old0-old0-old0\n";
    fakeOsv($csv);

    runOsv();
    expect(Issue::pluck('external_id')->all())->toBe(['GHSA-old0-old0-old0'])
        ->and(SyncCursor::sole()->cursor->toIso8601ZuluString())->toBe('2026-11-17T10:00:00Z');

    runOsv();
    expect(Issue::count())->toBe(3);
});

it('marks runs left running past the job timeout as interrupted', function () {
    $stale = SyncRun::create(['source' => 'osv', 'feed' => 'Packagist', 'status' => 'running', 'started_at' => now()->subMinutes(20)]);
    $fresh = SyncRun::create(['source' => 'osv', 'feed' => 'PyPI', 'status' => 'running', 'started_at' => now()->subMinute()]);
    fakeOsv();

    runOsv();

    expect($stale->fresh()->status)->toBe('failed')
        ->and($stale->fresh()->error)->toContain('Interrupted')
        ->and($fresh->fresh()->status)->toBe('running');
});

it('lets the unique lock expire and outlasts the job timeout in redis', function () {
    $job = new SyncFeed(OsvSource::class, 1, 'Packagist');

    expect($job->uniqueFor)->toBe(1800)
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan($job->timeout);
});
