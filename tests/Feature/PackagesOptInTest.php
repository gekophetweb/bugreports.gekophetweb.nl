<?php

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Jobs\NotifySubscribers;
use App\Livewire\Issues\Index;
use App\Livewire\Issues\Show;
use App\Livewire\My\Settings;
use App\Models\Issue;
use App\Models\IssueStar;
use App\Models\Language;
use App\Models\Subscription;
use App\Models\SyncRun;
use App\Models\User;
use App\Notifications\DigestNotification;
use App\Notifications\NewIssuesNotification;
use App\Sources\Ghsa\GhsaMapper;
use App\Sources\IssueUpserter;
use App\Sources\Nvd\NvdMapper;
use App\Sources\Osv\OsvMapper;
use App\Support\Issues\HiddenPackages;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->parse('2026-11-20 12:00:00', 'UTC'));
    $this->php = Language::factory()->create(['slug' => 'php', 'name' => 'PHP']);
    $this->go = Language::factory()->create(['slug' => 'go', 'name' => 'Go']);
});

function scoped(Language $language, string $id, string $scope, array $packages, array $attributes = []): Issue
{
    $issue = Issue::factory()->create($attributes + [
        'external_id' => $id, 'scope' => $scope, 'packages' => $packages, 'severity' => Severity::High,
        'published_at' => now()->subDay(), 'first_seen_at' => now()->subHour(),
    ]);
    $issue->languages()->attach($language);

    return $issue;
}

function follows(User $user, Language $language, bool $allPackages = false, SubscriptionFrequency $frequency = SubscriptionFrequency::Instant): Subscription
{
    return Subscription::create(['user_id' => $user->id, 'language_id' => $language->id, 'min_severity' => Severity::Low,
        'frequency' => $frequency, 'all_packages' => $allPackages]);
}

function listedFor(?User $user, array $params = [], array $cookies = []): array
{
    $test = Livewire::withQueryParams($params)->withCookies($cookies);
    $test = $user ? $test->actingAs($user) : $test;

    return $test->test(Index::class)->viewData('issues')->pluck('external_id')->sort()->values()->all();
}

// --- Scope ---

it('marks the language or server itself as core and packages as package', function () {
    $osv = app(OsvMapper::class);

    expect($osv->map(['id' => 'GHSA-1', 'affected' => [['package' => ['name' => 'laravel/framework', 'ecosystem' => 'Packagist']]]], 1)->scope)->toBe('package')
        ->and($osv->map(['id' => 'GO-1', 'affected' => [['package' => ['name' => 'stdlib', 'ecosystem' => 'Go']]]], 1)->scope)->toBe('core')
        ->and($osv->map(['id' => 'GO-2', 'affected' => [['package' => ['name' => 'toolchain', 'ecosystem' => 'Go']], ['package' => ['name' => 'stdlib', 'ecosystem' => 'Go']]]], 1)->scope)->toBe('core')
        ->and(app(NvdMapper::class)->map(['id' => 'CVE-1', 'descriptions' => []], 1, 'cpe:2.3:a:f5:nginx')->scope)->toBe('core')
        ->and(app(GhsaMapper::class)->map(['ghsa_id' => 'GHSA-2', 'summary' => 'x'], 1, 'php/php-src')->scope)->toBe('core');
});

it('keeps an issue core once a core source reported it', function () {
    $issue = scoped($this->php, 'GHSA-core', 'core', ['some/pkg']);
    $data = app(OsvMapper::class)->map(['id' => 'GHSA-core', 'summary' => 'Same', 'affected' => [['package' => ['name' => 'some/pkg']]]], $this->php->id);

    app(IssueUpserter::class)->upsert($data);

    expect($issue->fresh()->scope)->toBe('core');
});

// --- Lists ---

it('shows only the languages and servers themselves by default', function () {
    scoped($this->php, 'CORE', 'core', ['php']);
    scoped($this->php, 'PKG', 'package', ['laravel/framework']);

    $visitor = Livewire::test(Index::class);
    expect($visitor->viewData('issues')->pluck('external_id')->all())->toBe(['CORE'])
        ->and($visitor->viewData('hiddenCount'))->toBe(1)
        ->and(listedFor(User::factory()->create()))->toBe(['CORE'])
        ->and(listedFor(null, ['hidden' => '1']))->toBe(['CORE', 'PKG']);
});

it('shows the packages a user uses, by name or vendor, when any package of the issue matches', function () {
    scoped($this->php, 'CORE', 'core', ['php']);
    scoped($this->php, 'LARAVEL', 'package', ['laravel/framework']);
    scoped($this->php, 'MIXED', 'package', ['other/thing', 'symfony/http-kernel']);
    scoped($this->php, 'OTHER', 'package', ['other/thing']);
    $user = User::factory()->create();
    $user->update(['used_packages' => ['laravel/*', 'symfony/http-kernel']]);

    expect(listedFor($user))->toBe(['CORE', 'LARAVEL', 'MIXED']);
});

it('shows all packages of a language followed with All packages on', function () {
    scoped($this->php, 'PHP-PKG', 'package', ['laravel/framework']);
    scoped($this->go, 'GO-PKG', 'package', ['github.com/gin-gonic/gin']);
    $user = User::factory()->create();
    follows($user, $this->php, allPackages: true);
    follows($user, $this->go, allPackages: false);

    expect(listedFor($user))->toBe(['PHP-PKG']);
});

it('still hides hidden packages inside a language with All packages on', function () {
    scoped($this->php, 'SS', 'package', ['silverstripe/framework']);
    scoped($this->php, 'LARAVEL', 'package', ['laravel/framework']);
    $user = User::factory()->create();
    $user->update(['hidden_packages' => ['silverstripe/*']]);
    follows($user, $this->php, allPackages: true);

    expect(listedFor($user))->toBe(['LARAVEL']);
});

it('shows a starred package issue', function () {
    $starred = scoped($this->php, 'STARRED', 'package', ['laravel/framework']);
    $user = User::factory()->create();
    IssueStar::create(['user_id' => $user->id, 'issue_id' => $starred->id]);

    expect(listedFor($user))->toBe(['STARRED']);
});

it('lets users and visitors say they use a package', function () {
    $issue = scoped($this->php, 'LARAVEL', 'package', ['Laravel/Framework']);
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Show::class, ['id' => $issue->external_id])->call('usePackage', 'Laravel/Framework');
    expect($user->fresh()->used_packages)->toBe(['laravel/framework']);

    auth()->forgetGuards();
    Livewire::test(Index::class)->call('usePackage', 'laravel/framework');
    expect(json_decode(Cookie::queued(HiddenPackages::USED_COOKIE)->getValue(), true))->toBe(['laravel/framework'])
        ->and(listedFor(null, cookies: [HiddenPackages::USED_COOKIE => json_encode(['laravel/*'])]))->toBe(['LARAVEL']);
});

it('moves the packages a visitor uses into the account on sign-in', function () {
    $user = User::factory()->create();
    request()->cookies->set(HiddenPackages::USED_COOKIE, json_encode(['laravel/*']));

    event(new Login('web', $user, false));

    expect($user->fresh()->used_packages)->toBe(['laravel/*']);
});

// --- Settings ---

it('manages used packages and All packages per subscription in email settings', function () {
    $user = User::factory()->create();

    $settings = Livewire::actingAs($user)->test(Settings::class)
        ->set('newUsed', 'Laravel/*')->call('addUsed')->assertHasNoErrors()
        ->set('new.language_id', $this->php->id)->set('new.min_severity', 'high')->set('new.frequency', 'instant')
        ->call('subscribe');
    $sub = Subscription::sole();
    expect($user->fresh()->used_packages)->toBe(['laravel/*'])
        ->and($sub->all_packages)->toBeFalse();

    $settings->call('setAllPackages', $sub->id, true)->call('removeUsed', 'laravel/*');
    expect($sub->fresh()->all_packages)->toBeTrue()->and($user->fresh()->used_packages)->toBe([]);
});

// --- Emails ---

it('emails only core issues and used packages unless a subscription has All packages on', function () {
    Notification::fake();
    $core = scoped($this->php, 'CORE', 'core', ['php']);
    $laravel = scoped($this->php, 'LARAVEL', 'package', ['laravel/framework']);
    $other = scoped($this->php, 'OTHER', 'package', ['other/thing'], ['severity' => Severity::Critical]);
    $plain = User::factory()->create();
    follows($plain, $this->php);
    $user = User::factory()->create();
    $user->update(['used_packages' => ['laravel/*']]);
    follows($user, $this->php);
    $all = User::factory()->create();
    follows($all, $this->php, allPackages: true);
    $weekly = User::factory()->create();
    follows($weekly, $this->php, frequency: SubscriptionFrequency::Weekly);
    $run = SyncRun::create(['source' => 'osv', 'feed' => 'Packagist', 'status' => 'ok', 'started_at' => now(),
        'new_issue_ids' => [$core->id, $laravel->id, $other->id], 'changed_issues' => []]);

    NotifySubscribers::dispatchSync($run->id);

    $ids = fn (User $u) => fn ($n) => collect($n->issues)->pluck('external_id')->sort()->values()->all();
    Notification::assertSentTo($plain, NewIssuesNotification::class, fn ($n) => $ids($plain)($n) === ['CORE']);
    Notification::assertSentTo($user, NewIssuesNotification::class, fn ($n) => $ids($user)($n) === ['CORE', 'LARAVEL']);
    Notification::assertSentTo($all, NewIssuesNotification::class, fn ($n) => $ids($all)($n) === ['CORE', 'LARAVEL', 'OTHER']);
    Notification::assertNotSentTo($weekly, NewIssuesNotification::class); // OTHER is Critical but a package they do not use
});

it('leaves packages out of digests the same way', function () {
    Notification::fake();
    scoped($this->php, 'CORE', 'core', ['php']);
    scoped($this->php, 'PKG', 'package', ['laravel/framework']);
    $user = User::factory()->create();
    follows($user, $this->php, frequency: SubscriptionFrequency::Daily);

    $this->artisan('digest:send daily')->assertSuccessful();

    Notification::assertSentTo($user, DigestNotification::class,
        fn ($n) => collect($n->digest->full)->pluck('issue.external_id')->all() === ['CORE']);
});
