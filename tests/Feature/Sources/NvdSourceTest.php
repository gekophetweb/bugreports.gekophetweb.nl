<?php

use App\Enums\Severity;
use App\Jobs\SyncFeed;
use App\Models\Issue;
use App\Models\Language;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Sources\Nvd\NvdSource;
use App\Support\Sleeper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(now()->parse('2026-11-19 12:00:00', 'UTC'));
    $this->nginx = Language::factory()->create(['slug' => 'nginx', 'nvd_cpes' => ['cpe:2.3:a:f5:nginx']]);
    $this->sleeps = [];
    app()->instance(Sleeper::class, new class($this) extends Sleeper
    {
        public function __construct(private $test) {}

        public function sleep(float $seconds): void
        {
            $this->test->sleeps[] = $seconds;
        }
    });
});

function nvdPage(int $n): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/nvd/nginx-page$n.json")), true);
}

function fakeNvd(): void
{
    Http::fake(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

        return Http::response(($q['startIndex'] ?? '0') === '2000' ? nvdPage(2) : nvdPage(1));
    });
}

function runNvd(): SyncRun
{
    SyncFeed::dispatchSync(NvdSource::class, test()->nginx->id, 'cpe:2.3:a:f5:nginx');

    return SyncRun::query()->latest('id')->sole();
}

it('lists the CPEs as feeds', function () {
    expect(app(NvdSource::class)->feeds($this->nginx))->toBe(['cpe:2.3:a:f5:nginx']);
});

it('pages through the results', function () {
    fakeNvd();

    $run = runNvd();

    expect($run->status)->toBe('ok')->and($run->created_count)->toBe(3)
        ->and(Issue::pluck('external_id')->sort()->values()->all())->toBe(['CVE-2023-44487', 'CVE-2026-70001', 'CVE-2026-70002']);

    $starts = Http::recorded()->map(function ($pair) {
        parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $q);

        return [$q['startIndex'], $q['virtualMatchString'], $q['resultsPerPage'], $q['lastModStartDate'], $q['lastModEndDate']];
    })->all();
    expect($starts)->toBe([
        ['0', 'cpe:2.3:a:f5:nginx', '2000', '2026-08-21T12:00:00.000Z', '2026-11-19T12:00:00.000Z'],
        ['2000', 'cpe:2.3:a:f5:nginx', '2000', '2026-08-21T12:00:00.000Z', '2026-11-19T12:00:00.000Z'],
    ]);
    expect(SyncCursor::where(['source' => 'nvd', 'feed' => 'cpe:2.3:a:f5:nginx'])->sole()->cursor->toIso8601ZuluString())
        ->toBe('2026-11-19T12:00:00Z');
});

it('maps scores, severities and rejected CVEs', function () {
    fakeNvd();
    runNvd();

    $old = Issue::where('external_id', 'CVE-2023-44487')->sole();
    $critical = Issue::where('external_id', 'CVE-2026-70001')->sole();
    $rejected = Issue::where('external_id', 'CVE-2026-70002')->sole();

    expect($old->severity)->toBe(Severity::High)
        ->and($old->cvss_score)->toBe(7.5)
        ->and($old->source)->toBe('nvd')
        ->and($old->source_url)->toBe('https://nvd.nist.gov/vuln/detail/CVE-2023-44487')
        ->and($old->published_at->toIso8601ZuluString())->toBe('2023-10-10T14:15:10Z')
        ->and($old->packages)->toBe(['nginx'])
        ->and($old->title)->toStartWith('The HTTP/2 protocol allows a denial of service')
        ->and($critical->severity)->toBe(Severity::Critical)
        ->and($critical->title)->toBe('nginx before 1.29.4 allows request smuggling.')
        ->and($rejected->withdrawn_at)->not->toBeNull()
        ->and($rejected->severity)->toBe(Severity::Unknown);
});

it('sends the api key header when configured and waits less between requests', function () {
    config(['issues.nvd_api_key' => 'secret-key']);
    fakeNvd();

    runNvd();

    Http::assertSent(fn (Request $r) => $r->hasHeader('apiKey', 'secret-key'));
    expect($this->sleeps)->toHaveCount(1)->and($this->sleeps[0])->toBeGreaterThan(0.3)->toBeLessThanOrEqual(0.6);
});

it('waits six seconds between requests without a key', function () {
    fakeNvd();

    runNvd();

    Http::assertSent(fn (Request $r) => ! $r->hasHeader('apiKey'));
    expect($this->sleeps)->toHaveCount(1)->and($this->sleeps[0])->toBeGreaterThan(5.0)->toBeLessThanOrEqual(6.0);
});

it('splits long ranges into windows of at most 120 days', function () {
    config(['issues.backfill_days' => 300]);
    Http::fake(fn () => Http::response(['resultsPerPage' => 2000, 'startIndex' => 0, 'totalResults' => 0, 'vulnerabilities' => []]));

    runNvd();

    $windows = Http::recorded()->map(function ($pair) {
        parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $q);

        return [$q['lastModStartDate'], $q['lastModEndDate']];
    })->all();
    expect($windows)->toBe([
        ['2026-01-23T12:00:00.000Z', '2026-05-23T12:00:00.000Z'],
        ['2026-05-23T12:00:00.000Z', '2026-09-20T12:00:00.000Z'],
        ['2026-09-20T12:00:00.000Z', '2026-11-19T12:00:00.000Z'],
    ]);
});

it('records a failed run and keeps the cursor on 403', function () {
    Http::fake(fn () => Http::response('', 403));

    $run = runNvd();

    expect($run->status)->toBe('failed')->and($run->error)->toContain('403')->and(SyncCursor::count())->toBe(0);
});

it('spaces requests across separate sync jobs', function () {
    Http::fake(fn () => Http::response(['resultsPerPage' => 2000, 'startIndex' => 0, 'totalResults' => 0, 'vulnerabilities' => []]));
    $apache = Language::factory()->create(['slug' => 'apache', 'nvd_cpes' => ['cpe:2.3:a:apache:http_server']]);

    runNvd();
    SyncFeed::dispatchSync(NvdSource::class, $apache->id, 'cpe:2.3:a:apache:http_server');

    expect($this->sleeps)->toHaveCount(1)->and($this->sleeps[0])->toBeGreaterThan(5.0);
});

it('starts each run the lookback window before the cursor', function () {
    Http::fake(fn () => Http::response(['resultsPerPage' => 2000, 'startIndex' => 0, 'totalResults' => 0, 'vulnerabilities' => []]));
    runNvd();

    $this->travel(10)->minutes();
    SyncFeed::dispatchSync(NvdSource::class, $this->nginx->id, 'cpe:2.3:a:f5:nginx');

    parse_str((string) parse_url(Http::recorded()->last()[0]->url(), PHP_URL_QUERY), $q);
    expect([$q['lastModStartDate'], $q['lastModEndDate']])->toBe(['2026-11-19T10:00:00.000Z', '2026-11-19T12:10:00.000Z'])
        ->and(SyncCursor::sole()->cursor->toIso8601ZuluString())->toBe('2026-11-19T12:10:00Z');
});
