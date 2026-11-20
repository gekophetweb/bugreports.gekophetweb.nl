<?php

use App\Enums\Severity;
use App\Jobs\SyncFeed;
use App\Models\Issue;
use App\Models\Language;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Sources\SyncDispatcher;
use App\Sources\ZeroWire\ZeroWireSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const ZERO_WIRE_URL = 'radar.cloudflare.com/zero-wire/feed*';

beforeEach(function () {
    $this->travelTo(now()->parse('2026-11-20 12:00:00', 'UTC'));
    $this->php = Language::factory()->create(['slug' => 'php', 'zero_wire_tags' => ['PHP']]);
    $this->nginx = Language::factory()->create(['slug' => 'nginx', 'zero_wire_tags' => ['NGINX']]);
});

function zeroWireFixture(): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/zerowire/feed.json')), true);
}

function runZeroWire(Language $language): SyncRun
{
    SyncFeed::dispatchSync(ZeroWireSource::class, $language->id, $language->slug);

    return SyncRun::query()->latest('id')->first();
}

it('is a sync source with one feed per tagged language', function () {
    $untagged = Language::factory()->create();

    expect(SyncDispatcher::SOURCES)->toHaveKey('zerowire')
        ->and(app(ZeroWireSource::class)->feeds($this->php))->toBe(['php'])
        ->and(app(ZeroWireSource::class)->feeds($untagged))->toBe([]);
});

it('imports the briefings tagged with the language, within the backfill window', function () {
    Http::fake([ZERO_WIRE_URL => Http::response(zeroWireFixture())]);

    $run = runZeroWire($this->php);

    $issue = Issue::where('external_id', 'ZW-php-november-2026-security-release-phpaftershell')->sole();
    expect($run->status)->toBe('ok')
        ->and($run->created_count)->toBe(2) // the July briefing is older than the backfill window
        ->and($issue->source)->toBe('zerowire')
        ->and($issue->aliases)->toBe([])
        ->and($issue->description)->toStartWith('CVEs: CVE-2026-126714, CVE-2026-129289')
        ->and($issue->references)->toContain('https://nvd.nist.gov/vuln/detail/CVE-2026-126714')
        ->and($issue->references)->not->toContain('javascript:alert(1)')
        ->and($issue->source_url)->toBe('https://radar.cloudflare.com/zero-wire/php-november-2026-security-release-phpaftershell')
        ->and($issue->scope)->toBe('core')
        ->and($issue->severity)->toBe(Severity::High)
        ->and($issue->languages->pluck('slug')->all())->toBe(['php'])
        ->and(Issue::where('external_id', 'ZW-wordpress-7-1-5-template-part-privilege-escalation')->sole()->scope)->toBe('package')
        ->and(Issue::where('external_id', 'ZW-nginx2root')->exists())->toBeFalse()
        ->and(SyncCursor::where(['source' => 'zerowire', 'feed' => 'php'])->sole()->cursor->toDateString())->toBe('2026-11-19');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'format=json'));
});

it('matches tags without regard to case', function () {
    Http::fake([ZERO_WIRE_URL => Http::response(zeroWireFixture())]);

    runZeroWire($this->nginx);

    expect(Issue::where('external_id', 'ZW-nginx2root')->sole()->languages->pluck('slug')->all())->toBe(['nginx']);
});

it('takes the highest severity of the CVEs we already have', function () {
    Issue::factory()->create(['external_id' => 'GHSA-fm2m-fjf5-v3cp', 'aliases' => ['CVE-2026-126714'], 'severity' => Severity::Critical]);
    Http::fake([ZERO_WIRE_URL => Http::response(zeroWireFixture())]);

    runZeroWire($this->php);

    $briefing = Issue::where('external_id', 'ZW-php-november-2026-security-release-phpaftershell')->sole();
    expect($briefing->severity)->toBe(Severity::Critical)
        // the advisory is not merged into the briefing, nor the other way round
        ->and(Issue::where('external_id', 'GHSA-fm2m-fjf5-v3cp')->sole()->aliases)->toBe(['CVE-2026-126714']);
});

it('does not duplicate a briefing on the next run', function () {
    Http::fake([ZERO_WIRE_URL => Http::response(zeroWireFixture())]);

    runZeroWire($this->php);
    $second = runZeroWire($this->php);

    expect($second->created_count)->toBe(0)->and(Issue::where('source', 'zerowire')->count())->toBe(2);
});

it('fails the run when the feed cannot be fetched', function () {
    Http::fake([ZERO_WIRE_URL => Http::response('nope', 503)]);

    $run = runZeroWire($this->php);

    expect($run->status)->toBe('failed')->and($run->error)->toContain('HTTP 503');
});
