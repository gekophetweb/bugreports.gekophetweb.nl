<?php

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Models\Issue;
use App\Models\IssueChange;
use App\Models\IssueWatch;
use App\Models\Language;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\DigestNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->php = Language::factory()->create(['slug' => 'php', 'name' => 'PHP']);
});

function digestSubscriber(Language $language, SubscriptionFrequency $frequency, Severity $min = Severity::Low): User
{
    $user = User::factory()->create();
    Subscription::create(['user_id' => $user->id, 'language_id' => $language->id, 'min_severity' => $min, 'frequency' => $frequency]);

    return $user;
}

function seenIssue(Language $language, Severity $severity, int $hoursAgo): Issue
{
    $issue = Issue::factory()->create([
        'severity' => $severity, 'published_at' => now()->subHours($hoursAgo + 1), 'first_seen_at' => now()->subHours($hoursAgo),
    ]);
    $issue->languages()->attach($language);

    return $issue;
}

it('sends a daily digest of the last 24 hours', function () {
    $user = digestSubscriber($this->php, SubscriptionFrequency::Daily);
    $recent = seenIssue($this->php, Severity::High, 2);
    seenIssue($this->php, Severity::High, 30);

    $this->artisan('digest:send daily')->assertSuccessful();

    Notification::assertSentToTimes($user, DigestNotification::class, 1);
    Notification::assertSentTo($user, DigestNotification::class,
        fn ($n) => collect($n->digest->full)->pluck('issue.id')->all() === [$recent->id] && $n->period === 'daily');
});

it('sends a weekly digest of 7 days including watched changes', function () {
    $user = digestSubscriber($this->php, SubscriptionFrequency::Weekly);
    seenIssue($this->php, Severity::High, 24 * 6);
    seenIssue($this->php, Severity::High, 24 * 8);
    $watched = Issue::factory()->create(['published_at' => now()->subYear()]);
    IssueWatch::create(['user_id' => $user->id, 'issue_id' => $watched->id]);
    IssueChange::create(['issue_id' => $watched->id, 'fields' => ['severity'], 'previous_severity' => Severity::Medium]);

    $this->artisan('digest:send weekly')->assertSuccessful();

    Notification::assertSentTo($user, DigestNotification::class,
        fn ($n) => count($n->digest->full) === 1 && count($n->digest->watched) === 1 && $n->digest->watched[0]['fields'] === ['severity']);
});

it('respects the minimum severity and labels Critical issues as already alerted', function () {
    $user = digestSubscriber($this->php, SubscriptionFrequency::Weekly, Severity::High);
    $critical = seenIssue($this->php, Severity::Critical, 5);
    seenIssue($this->php, Severity::Medium, 5);

    $this->artisan('digest:send weekly');

    Notification::assertSentTo($user, DigestNotification::class,
        fn ($n) => collect($n->digest->full)->pluck('issue.id')->all() === [$critical->id] && $n->digest->full[0]['alerted'] === true);
});

it('only sends to subscribers of that frequency and skips empty digests', function () {
    $daily = digestSubscriber($this->php, SubscriptionFrequency::Daily);
    $instant = digestSubscriber($this->php, SubscriptionFrequency::Instant);
    seenIssue($this->php, Severity::High, 2);
    $quiet = digestSubscriber(Language::factory()->create(), SubscriptionFrequency::Daily);

    $this->artisan('digest:send daily');

    Notification::assertSentTo($daily, DigestNotification::class);
    Notification::assertNotSentTo($instant, DigestNotification::class);
    Notification::assertNotSentTo($quiet, DigestNotification::class);
});

it('rejects an unknown period', function () {
    $this->artisan('digest:send monthly')->assertFailed();
});

it('renders the digest email with full, short and overflow parts', function () {
    $user = digestSubscriber($this->php, SubscriptionFrequency::Weekly);
    foreach (range(1, 125) as $i) {
        seenIssue($this->php, Severity::High, 3);
    }
    Notification::fake();
    $this->artisan('digest:send weekly');

    Notification::assertSentTo($user, DigestNotification::class, function ($n) use ($user) {
        $html = (string) $n->toMail($user)->render();

        return str_contains($html, 'and 5 more') && str_contains($html, route('my.issues'));
    });
});

it('marks issues a straight-away subscription of the same language already emailed', function () {
    // PHP Critical straight away, PHP Medium and up weekly.
    $user = digestSubscriber($this->php, SubscriptionFrequency::Weekly, Severity::Medium);
    Subscription::create(['user_id' => $user->id, 'language_id' => $this->php->id, 'min_severity' => Severity::High, 'frequency' => SubscriptionFrequency::Instant]);
    $high = seenIssue($this->php, Severity::High, 5);
    $medium = seenIssue($this->php, Severity::Medium, 5);

    $this->artisan('digest:send weekly');

    Notification::assertSentTo($user, DigestNotification::class, function ($n) use ($high, $medium) {
        $alerted = collect($n->digest->full)->mapWithKeys(fn ($i) => [$i['issue']->id => $i['alerted']])->all();

        return $alerted === [$high->id => true, $medium->id => false] && count($n->subscriptions) === 1;
    });
});
