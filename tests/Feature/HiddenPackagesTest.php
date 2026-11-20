<?php

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Jobs\NotifySubscribers;
use App\Livewire\Issues\Index;
use App\Livewire\Issues\Show;
use App\Livewire\My\Issues as MyIssues;
use App\Livewire\My\Settings;
use App\Models\Issue;
use App\Models\IssueChange;
use App\Models\IssueStar;
use App\Models\IssueWatch;
use App\Models\Language;
use App\Models\Subscription;
use App\Models\SyncRun;
use App\Models\User;
use App\Notifications\DigestNotification;
use App\Notifications\NewIssuesNotification;
use App\Notifications\WatchedIssuesUpdatedNotification;
use App\Support\Issues\HiddenPackages;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now()->parse('2026-11-20 12:00:00', 'UTC'));
    $this->php = Language::factory()->create(['slug' => 'php', 'name' => 'PHP']);
});

function pkgIssue(Language $language, string $id, array $packages, array $attributes = []): Issue
{
    $issue = Issue::factory()->create($attributes + [
        'external_id' => $id, 'packages' => $packages, 'severity' => Severity::High,
        'published_at' => now()->subDay(), 'first_seen_at' => now()->subHour(),
    ]);
    $issue->languages()->attach($language);

    return $issue;
}

function hider(array $hidden): User
{
    $user = User::factory()->create();
    $user->update(['hidden_packages' => $hidden]);

    return $user;
}

// --- Matching ---

it('hides an issue only when every package is hidden', function () {
    $hidden = ['silverstripe/framework', 'acme/*'];

    expect(HiddenPackages::hides($hidden, ['silverstripe/framework']))->toBeTrue()
        ->and(HiddenPackages::hides($hidden, ['SilverStripe/Framework']))->toBeTrue()
        ->and(HiddenPackages::hides($hidden, ['acme/one', 'acme/two']))->toBeTrue()
        ->and(HiddenPackages::hides($hidden, ['silverstripe/framework', 'laravel/framework']))->toBeFalse()
        ->and(HiddenPackages::hides($hidden, ['silverstripe/cms']))->toBeFalse()
        ->and(HiddenPackages::hides($hidden, ['acmes/thing']))->toBeFalse()
        ->and(HiddenPackages::hides($hidden, []))->toBeFalse()
        ->and(HiddenPackages::hides([], ['silverstripe/framework']))->toBeFalse();
});

it('normalizes and validates package patterns', function () {
    expect(HiddenPackages::normalize('  SilverStripe/Framework '))->toBe('silverstripe/framework')
        ->and(HiddenPackages::isValid('silverstripe/*'))->toBeTrue()
        ->and(HiddenPackages::isValid('@scope/name'))->toBeTrue()
        ->and(HiddenPackages::isValid('golang.org/x/net'))->toBeTrue()
        ->and(HiddenPackages::isValid('*'))->toBeFalse()
        ->and(HiddenPackages::isValid('a b'))->toBeFalse()
        ->and(HiddenPackages::isValid('silver*stripe'))->toBeFalse();
});

// --- Lists ---

it('leaves hidden packages out of the issue list for that user only', function () {
    pkgIssue($this->php, 'SS', ['silverstripe/framework']);
    pkgIssue($this->php, 'MIXED', ['silverstripe/framework', 'laravel/framework']);
    pkgIssue($this->php, 'NONE', []);
    $user = hider(['silverstripe/framework']);

    $mine = Livewire::actingAs($user)->test(Index::class);
    expect($mine->viewData('issues')->pluck('external_id')->sort()->values()->all())->toBe(['MIXED', 'NONE'])
        ->and($mine->viewData('hiddenCount'))->toBe(1);

    auth()->forgetGuards();
    $guest = Livewire::test(Index::class);
    expect($guest->viewData('issues')->pluck('external_id')->sort()->values()->all())->toBe(['MIXED', 'NONE', 'SS']);
});

it('shows hidden issues on request', function () {
    pkgIssue($this->php, 'SS', ['silverstripe/framework']);
    $user = hider(['silverstripe/*']);

    $list = Livewire::actingAs($user)->withQueryParams(['hidden' => '1'])->test(Index::class);

    expect($list->viewData('issues')->pluck('external_id')->all())->toBe(['SS']);
    $this->actingAs($user)->get('/')->assertSee('1 hidden');
});

it('keeps starred and watched issues visible even when their package is hidden', function () {
    $starred = pkgIssue($this->php, 'STARRED', ['silverstripe/framework']);
    $watched = pkgIssue($this->php, 'WATCHED', ['silverstripe/framework']);
    pkgIssue($this->php, 'OTHER', ['silverstripe/framework']);
    $user = hider(['silverstripe/framework']);
    IssueStar::create(['user_id' => $user->id, 'issue_id' => $starred->id]);
    IssueWatch::create(['user_id' => $user->id, 'issue_id' => $watched->id]);

    $ids = Livewire::actingAs($user)->test(Index::class)->viewData('issues')->pluck('external_id')->sort()->values()->all();

    expect($ids)->toBe(['STARRED', 'WATCHED']);
});

it('applies to My issues too', function () {
    pkgIssue($this->php, 'SS', ['silverstripe/framework']);
    pkgIssue($this->php, 'OK', ['laravel/framework']);
    $user = hider(['silverstripe/framework']);
    Subscription::create(['user_id' => $user->id, 'language_id' => $this->php->id, 'min_severity' => Severity::Low, 'frequency' => SubscriptionFrequency::Instant]);

    $ids = Livewire::actingAs($user)->test(MyIssues::class)->viewData('issues')->pluck('external_id')->all();

    expect($ids)->toBe(['OK']);
});

it('hides a package from the list and from the issue page', function () {
    $issue = pkgIssue($this->php, 'SS', ['SilverStripe/Framework']);
    $user = hider([]);

    Livewire::actingAs($user)->test(Index::class)->call('hidePackage', 'SilverStripe/Framework');
    expect($user->fresh()->hidden_packages)->toBe(['silverstripe/framework']);

    Livewire::actingAs($user)->test(Show::class, ['id' => $issue->external_id])->call('hidePackage', 'other/pkg');
    expect($user->fresh()->hidden_packages)->toBe(['silverstripe/framework', 'other/pkg']);
});

// --- Email settings ---

it('adds and removes hidden packages in email settings', function () {
    $user = hider([]);

    Livewire::actingAs($user)->test(Settings::class)
        ->set('newHidden', ' SilverStripe/* ')->call('addHidden')->assertHasNoErrors()
        ->set('newHidden', 'not valid')->call('addHidden')->assertHasErrors('newHidden');
    expect($user->fresh()->hidden_packages)->toBe(['silverstripe/*']);

    Livewire::actingAs($user)->test(Settings::class)->call('removeHidden', 'silverstripe/*');
    expect($user->fresh()->hidden_packages)->toBe([]);
});

// --- Emails ---

it('leaves hidden packages out of instant emails and Critical alerts', function () {
    Notification::fake();
    $instant = hider(['silverstripe/framework']);
    $weekly = hider(['silverstripe/framework']);
    Subscription::create(['user_id' => $instant->id, 'language_id' => $this->php->id, 'min_severity' => Severity::Low, 'frequency' => SubscriptionFrequency::Instant]);
    Subscription::create(['user_id' => $weekly->id, 'language_id' => $this->php->id, 'min_severity' => Severity::Low, 'frequency' => SubscriptionFrequency::Weekly]);
    $hidden = pkgIssue($this->php, 'SS', ['silverstripe/framework'], ['severity' => Severity::Critical]);
    $shown = pkgIssue($this->php, 'OK', ['laravel/framework']);
    $run = SyncRun::create(['source' => 'osv', 'feed' => 'Packagist', 'status' => 'ok', 'started_at' => now(), 'new_issue_ids' => [$hidden->id, $shown->id], 'changed_issues' => []]);

    NotifySubscribers::dispatchSync($run->id);

    Notification::assertSentTo($instant, NewIssuesNotification::class, fn ($n) => collect($n->issues)->pluck('external_id')->all() === ['OK']);
    Notification::assertNotSentTo($weekly, NewIssuesNotification::class);
});

it('leaves hidden packages out of Critical updates but not out of watched updates', function () {
    Notification::fake();
    $user = hider(['silverstripe/framework']);
    Subscription::create(['user_id' => $user->id, 'language_id' => $this->php->id, 'min_severity' => Severity::Low, 'frequency' => SubscriptionFrequency::Instant]);
    $critical = pkgIssue($this->php, 'CRIT', ['silverstripe/framework'], ['severity' => Severity::Critical]);
    $watched = pkgIssue($this->php, 'WATCHED', ['silverstripe/framework']);
    IssueWatch::create(['user_id' => $user->id, 'issue_id' => $watched->id]);
    $run = SyncRun::create(['source' => 'osv', 'feed' => 'Packagist', 'status' => 'ok', 'started_at' => now(), 'new_issue_ids' => [],
        'changed_issues' => [(string) $critical->id => ['description'], (string) $watched->id => ['description']]]);
    foreach ([$critical, $watched] as $issue) {
        IssueChange::create(['issue_id' => $issue->id, 'sync_run_id' => $run->id, 'fields' => ['description'], 'previous_severity' => $issue->severity]);
    }

    NotifySubscribers::dispatchSync($run->id);

    Notification::assertSentTo($user, WatchedIssuesUpdatedNotification::class,
        fn ($n) => collect($n->items)->pluck('issue.external_id')->all() === ['WATCHED']);
});

it('leaves hidden packages out of digests', function () {
    Notification::fake();
    $user = hider(['silverstripe/framework']);
    Subscription::create(['user_id' => $user->id, 'language_id' => $this->php->id, 'min_severity' => Severity::Low, 'frequency' => SubscriptionFrequency::Daily]);
    pkgIssue($this->php, 'SS', ['silverstripe/framework']);
    pkgIssue($this->php, 'OK', ['laravel/framework']);

    $this->artisan('digest:send daily')->assertSuccessful();

    Notification::assertSentTo($user, DigestNotification::class,
        fn ($n) => collect($n->digest->full)->pluck('issue.external_id')->all() === ['OK']);
});

// --- Visitors (cookie) ---

it('lets visitors hide a package in a cookie', function () {
    Livewire::test(Index::class)->call('hidePackage', 'SilverStripe/Framework')->assertNoRedirect();

    expect(Cookie::hasQueued(HiddenPackages::COOKIE))->toBeTrue()
        ->and(json_decode(Cookie::queued(HiddenPackages::COOKIE)->getValue(), true))->toBe(['silverstripe/framework']);
});

it('leaves the packages in the visitor cookie out of the list, and can show them again', function () {
    pkgIssue($this->php, 'SS', ['silverstripe/framework']);
    pkgIssue($this->php, 'OK', ['laravel/framework']);
    $cookie = [HiddenPackages::COOKIE => json_encode(['silverstripe/*'])];

    $list = Livewire::withCookies($cookie)->test(Index::class);
    expect($list->viewData('issues')->pluck('external_id')->all())->toBe(['OK'])
        ->and($list->viewData('hiddenCount'))->toBe(1)
        ->and($list->viewData('hiddenPackages'))->toBe(['silverstripe/*']);

    $list->call('unhidePackage', 'silverstripe/*');
    expect(json_decode(Cookie::queued(HiddenPackages::COOKIE)->getValue(), true))->toBe([]);
});

it('ignores a tampered visitor cookie', function () {
    pkgIssue($this->php, 'SS', ['silverstripe/framework']);

    $list = Livewire::withCookies([HiddenPackages::COOKIE => '{"not":"a list"} or worse'])->test(Index::class);

    expect($list->viewData('issues')->pluck('external_id')->all())->toBe(['SS']);
});

it('moves the visitor cookie into the account on sign-in', function () {
    $user = hider(['acme/*']);
    request()->cookies->set(HiddenPackages::COOKIE, json_encode(['silverstripe/framework', 'acme/*']));

    event(new Login('web', $user, false));

    expect($user->fresh()->hidden_packages)->toBe(['acme/*', 'silverstripe/framework'])
        ->and(Cookie::queued(HiddenPackages::COOKIE)->getExpiresTime())->toBeLessThan(time());
});
