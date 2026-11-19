<?php

use App\Enums\Role;
use App\Enums\Severity;
use App\Jobs\SyncFeed;
use App\Livewire\Admin\Mail as MailPage;
use App\Livewire\Admin\Users;
use App\Models\Issue;
use App\Models\Language;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Models\User;
use App\Notifications;
use App\Sources\IssueData;
use App\Sources\IssueUpserter;
use App\Sources\Nvd\NvdMapper;
use App\Sources\Osv\OsvMapper;
use App\Sources\Osv\OsvSource;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function data(array $o): IssueData
{
    return new IssueData(...array_merge([
        'source' => 'osv', 'externalId' => 'X', 'aliases' => [], 'title' => 'T', 'description' => 'D',
        'severity' => Severity::Unknown, 'cvssScore' => null, 'cvssVector' => null, 'packages' => [], 'references' => [],
        'sourceUrl' => 'https://osv.dev/vulnerability/X', 'publishedAt' => CarbonImmutable::parse('2026-11-10'),
        'modifiedAt' => CarbonImmutable::parse('2026-11-10'), 'withdrawnAt' => null, 'languageId' => 0,
    ], $o));
}

beforeEach(function () {
    $this->go = Language::factory()->create(['slug' => 'go']);
    $this->up = app(IssueUpserter::class);
});

// I-1
it('keeps an NVD score when a later OSV update has none', function () {
    $this->up->upsert(data(['externalId' => 'GO-2026-1', 'aliases' => ['CVE-2026-1'], 'languageId' => $this->go->id]));
    $this->up->upsert(data(['source' => 'nvd', 'externalId' => 'CVE-2026-1', 'severity' => Severity::Critical, 'cvssScore' => 9.8,
        'cvssVector' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 'languageId' => $this->go->id]));

    $result = $this->up->upsert(data(['externalId' => 'GO-2026-1', 'aliases' => ['CVE-2026-1'], 'description' => 'D',
        'modifiedAt' => CarbonImmutable::parse('2026-11-12'), 'languageId' => $this->go->id]));

    expect(Issue::sole()->severity)->toBe(Severity::Critical)->and(Issue::sole()->cvss_score)->toBe(9.8)
        ->and($result->changes)->toBe([]);
});

// I-2
it('merges two OSV advisories for the same vulnerability into one issue', function () {
    $this->up->upsert(data(['externalId' => 'PYSEC-2026-1', 'aliases' => ['CVE-2026-2', 'GHSA-aaaa-bbbb-cccc'], 'languageId' => $this->go->id]));

    $result = $this->up->upsert(data(['externalId' => 'GHSA-aaaa-bbbb-cccc', 'aliases' => ['CVE-2026-2', 'PYSEC-2026-1'],
        'severity' => Severity::High, 'cvssScore' => 7.5, 'languageId' => $this->go->id]));

    $issue = Issue::sole();
    expect($result->created)->toBeFalse()
        ->and($issue->external_id)->toBe('PYSEC-2026-1')
        ->and($issue->aliases)->toContain('GHSA-aaaa-bbbb-cccc')->toContain('CVE-2026-2')
        ->and($issue->severity)->toBe(Severity::High);
});

it('merges OSV advisories that only share a CVE alias, and keeps them merged on later updates', function () {
    $this->up->upsert(data(['externalId' => 'GO-2026-3', 'aliases' => ['CVE-2026-3'], 'languageId' => $this->go->id]));
    $this->up->upsert(data(['externalId' => 'GHSA-dddd-eeee-ffff', 'aliases' => ['CVE-2026-3'], 'languageId' => $this->go->id]));
    $this->up->upsert(data(['externalId' => 'GO-2026-3', 'aliases' => ['CVE-2026-3'], 'description' => 'Changed', 'languageId' => $this->go->id]));
    $this->up->upsert(data(['externalId' => 'GHSA-dddd-eeee-ffff', 'aliases' => ['CVE-2026-3'], 'languageId' => $this->go->id]));

    expect(Issue::count())->toBe(1)->and(Issue::sole()->aliases)->toContain('GHSA-dddd-eeee-ffff');
});

// I-3
it('skips an OSV record that no longer exists instead of stalling the feed', function () {
    $this->travelTo(now()->parse('2026-11-19 12:00:00', 'UTC'));
    $php = Language::factory()->create(['slug' => 'php', 'osv_ecosystems' => ['Packagist']]);
    $ghsa = json_decode(file_get_contents(base_path('tests/Fixtures/osv/GHSA-82w8-65qw-gch6.json')), true);
    Http::fake([
        'osv-vulnerabilities.storage.googleapis.com/*' => Http::response("2026-11-18T21:00:39Z,GHSA-82w8-65qw-gch6\n2026-11-17T10:00:00Z,GHSA-gone-gone-gone\n"),
        'api.osv.dev/v1/vulns/GHSA-gone-gone-gone' => Http::response('', 404),
        'api.osv.dev/v1/vulns/GHSA-82w8-65qw-gch6' => Http::response($ghsa),
    ]);

    SyncFeed::dispatchSync(OsvSource::class, $php->id, 'Packagist');

    expect(SyncRun::sole()->status)->toBe('ok')->and(Issue::where('external_id', 'GHSA-82w8-65qw-gch6')->exists())->toBeTrue()
        ->and(SyncCursor::sole()->cursor->toIso8601ZuluString())->toBe('2026-11-18T21:00:39Z');
});

// I-4
it('sends the test email with the settings saved in the database, not the ones from worker start', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Superadmin]));
    Settings::set('mail.mailer', 'array');
    config(['mail.default' => 'log']); // what an Octane worker cloned at boot

    Livewire::test(MailPage::class)->call('sendTest')->assertHasNoErrors();

    expect(config('mail.default'))->toBe('array')
        ->and(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages())->toHaveCount(1);
});

it('applies the stored mail settings on every web request', function () {
    Settings::set('mail.mailer', 'array');
    config(['mail.default' => 'log']);

    $this->get('/up');
    $this->get('/');

    expect(config('mail.default'))->toBe('array');
});

// I-5
it('refuses admin actions from a superadmin who was demoted after opening the page', function () {
    $other = User::factory()->create(['role' => Role::Superadmin]);
    $demoted = User::factory()->create(['role' => Role::Superadmin]);
    $this->actingAs($demoted);
    $page = Livewire::test(Users::class);

    $demoted->update(['role' => Role::User]);
    $page->call('setRole', $demoted->id, 'superadmin')->assertForbidden();

    expect($demoted->fresh()->role)->toBe(Role::User);
});

// I-6
it('sends emails on the mail queue so syncs cannot delay them', function (string $class) {
    $n = (new ReflectionClass($class))->newInstanceWithoutConstructor();

    expect($n->viaQueues())->toBe(['mail' => 'mail']);
})->with([
    Notifications\LoginCodeNotification::class, Notifications\NewIssuesNotification::class,
    Notifications\WatchedIssuesUpdatedNotification::class, Notifications\DigestNotification::class,
    Notifications\FeedbackReceived::class, Notifications\FeedbackStatusChanged::class,
]);

it('runs a separate worker for the mail queue', function () {
    $compose = file_get_contents(base_path('docker-compose.yml'));

    expect($compose)->toContain('queue:work --queue=mail');
});

// I-7
it('redirects an old issue id to the issue that took it over', function () {
    Issue::factory()->create(['external_id' => 'GHSA-new0-new0-new0', 'aliases' => ['CVE-2026-9']]);

    $this->get('/issues/CVE-2026-9')->assertRedirect('/issues/GHSA-new0-new0-new0');
});

// M-1 (regraded)
it('keeps only http and https reference links from the sources', function () {
    $osv = (new OsvMapper)->map(['id' => 'X-1', 'modified' => '2026-11-01T00:00:00Z', 'references' => [
        ['url' => 'javascript:alert(1)'], ['url' => 'https://example.test/a'], ['url' => 'data:text/html,x'], ['url' => 'http://example.test/b'],
    ]], 1);
    $nvd = (new NvdMapper)->map(['id' => 'CVE-1', 'references' => [['url' => 'JavaScript:alert(1)'], ['url' => 'https://nvd.test']]], 1, 'cpe:2.3:a:f5:nginx');

    expect($osv->references)->toBe(['https://example.test/a', 'http://example.test/b'])
        ->and($nvd->references)->toBe(['https://nvd.test']);
});

it('does not render a script link already stored', function () {
    Issue::factory()->create(['external_id' => 'X-2', 'references' => ['javascript:alert(1)', 'https://ok.test']]);

    $this->get('/issues/X-2')->assertOk()->assertDontSee('javascript:alert(1)', false)->assertSee('https://ok.test');
});
