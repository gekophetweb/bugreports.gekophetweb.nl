<?php

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Jobs\NotifySubscribers;
use App\Jobs\SyncFeed;
use App\Models\Issue;
use App\Models\IssueChange;
use App\Models\IssueWatch;
use App\Models\Language;
use App\Models\Subscription;
use App\Models\SyncRun;
use App\Models\User;
use App\Notifications\NewIssuesNotification;
use App\Notifications\WatchedIssuesUpdatedNotification;
use App\Sources\Osv\OsvSource;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Notification::fake();
    $this->php = Language::factory()->create(['slug' => 'php', 'name' => 'PHP']);
    $this->go = Language::factory()->create(['slug' => 'go', 'name' => 'Go']);
});

function subscriber(Language $language, Severity $min, SubscriptionFrequency $frequency): User
{
    $user = User::factory()->create();
    Subscription::create(['user_id' => $user->id, 'language_id' => $language->id, 'min_severity' => $min, 'frequency' => $frequency]);

    return $user;
}

function newIssue(Language $language, Severity $severity, array $attributes = []): Issue
{
    $issue = Issue::factory()->create($attributes + ['severity' => $severity, 'published_at' => now()->subDay(), 'first_seen_at' => now()]);
    $issue->languages()->attach($language);

    return $issue;
}

function runWith(array $newIds = [], array $changed = []): SyncRun
{
    return SyncRun::create([
        'source' => 'osv', 'feed' => 'Packagist', 'status' => 'ok', 'started_at' => now(),
        'new_issue_ids' => $newIds, 'changed_issues' => $changed,
    ]);
}

/** Records a meaningful change on $issue in $run, as IssueUpserter does. */
function changeIssue(Issue $issue, SyncRun $run, array $fields, Severity $previous): void
{
    IssueChange::create(['issue_id' => $issue->id, 'sync_run_id' => $run->id, 'fields' => $fields, 'previous_severity' => $previous]);
    $changed = $run->changed_issues;
    $changed[(string) $issue->id] = $fields;
    $run->update(['changed_issues' => $changed]);
}

function notify(SyncRun $run): void
{
    NotifySubscribers::dispatchSync($run->id);
}

// --- New issues ---

it('sends one email per user per run listing the new matching issues', function () {
    $user = subscriber($this->php, Severity::High, SubscriptionFrequency::Instant);
    $ids = [newIssue($this->php, Severity::High)->id, newIssue($this->php, Severity::Critical)->id, newIssue($this->php, Severity::High)->id];

    notify(runWith($ids));

    Notification::assertSentToTimes($user, NewIssuesNotification::class, 1);
    Notification::assertSentTo($user, NewIssuesNotification::class, fn ($n) => count($n->issues) === 3);
});

it('skips issues below the minimum severity', function () {
    $user = subscriber($this->php, Severity::High, SubscriptionFrequency::Instant);

    notify(runWith([newIssue($this->php, Severity::Medium)->id]));

    Notification::assertNotSentTo($user, NewIssuesNotification::class);
});

it('stays quiet for backfilled old issues', function () {
    $user = subscriber($this->php, Severity::Low, SubscriptionFrequency::Instant);

    notify(runWith([newIssue($this->php, Severity::Critical, ['published_at' => now()->subDays(30)])->id]));

    Notification::assertNothingSent();
});

it('only mails subscribers of the issue language', function () {
    $goUser = subscriber($this->go, Severity::Low, SubscriptionFrequency::Instant);

    notify(runWith([newIssue($this->php, Severity::Critical)->id]));

    Notification::assertNotSentTo($goUser, NewIssuesNotification::class);
});

it('alerts daily and weekly subscribers instantly about new Critical issues only', function () {
    $weekly = subscriber($this->php, Severity::Low, SubscriptionFrequency::Weekly);
    $instant = subscriber($this->php, Severity::Low, SubscriptionFrequency::Instant);
    $critical = newIssue($this->php, Severity::Critical);

    notify(runWith([$critical->id, newIssue($this->php, Severity::High)->id, newIssue($this->php, Severity::High)->id]));

    Notification::assertSentToTimes($weekly, NewIssuesNotification::class, 1);
    Notification::assertSentTo($weekly, NewIssuesNotification::class,
        fn ($n) => collect($n->issues)->pluck('id')->all() === [$critical->id] && str_starts_with($n->subject(), 'Critical:'));
    Notification::assertSentToTimes($instant, NewIssuesNotification::class, 1);
    Notification::assertSentTo($instant, NewIssuesNotification::class, fn ($n) => count($n->issues) === 3);
});

it('does not alert about a backfilled Critical issue', function () {
    $weekly = subscriber($this->php, Severity::Low, SubscriptionFrequency::Weekly);

    notify(runWith([newIssue($this->php, Severity::Critical, ['published_at' => now()->subDays(30)])->id]));

    Notification::assertNotSentTo($weekly, NewIssuesNotification::class);
});

it('sends nothing to daily subscribers for new non-Critical issues', function () {
    $daily = subscriber($this->php, Severity::Low, SubscriptionFrequency::Daily);

    notify(runWith([newIssue($this->php, Severity::High)->id]));

    Notification::assertNothingSent();
});

// --- Updates to watched and Critical issues ---

it('emails watchers when a watched issue changes', function () {
    $user = User::factory()->create();
    $issue = newIssue($this->php, Severity::Critical, ['published_at' => now()->subDays(200)]);
    IssueWatch::create(['user_id' => $user->id, 'issue_id' => $issue->id]);
    $run = runWith();
    changeIssue($issue, $run, ['severity'], Severity::High);

    notify($run);

    Notification::assertSentToTimes($user, WatchedIssuesUpdatedNotification::class, 1);
    Notification::assertSentTo($user, WatchedIssuesUpdatedNotification::class,
        fn ($n) => $n->items[0]['fields'] === ['severity'] && $n->items[0]['tag'] === 'watched');
});

it('does not email anyone about a change to an unwatched non-Critical issue', function () {
    subscriber($this->php, Severity::Low, SubscriptionFrequency::Instant);
    $issue = newIssue($this->php, Severity::High);
    $run = runWith();
    changeIssue($issue, $run, ['description'], Severity::High);

    notify($run);

    Notification::assertNothingSent();
});

it('emails every language subscriber when a recent Critical issue changes', function () {
    $weekly = subscriber($this->php, Severity::High, SubscriptionFrequency::Weekly);
    $issue = newIssue($this->php, Severity::Critical);
    $run = runWith();
    changeIssue($issue, $run, ['cvss_score'], Severity::Critical);

    notify($run);

    Notification::assertSentToTimes($weekly, WatchedIssuesUpdatedNotification::class, 1);
    Notification::assertSentTo($weekly, WatchedIssuesUpdatedNotification::class, fn ($n) => $n->items[0]['tag'] === 'critical');
});

it('emails subscribers when an issue becomes Critical and when it stops being Critical', function () {
    $user = subscriber($this->php, Severity::Low, SubscriptionFrequency::Daily);
    $raised = newIssue($this->php, Severity::Critical);
    $lowered = newIssue($this->php, Severity::High);
    $run = runWith();
    changeIssue($raised, $run, ['severity', 'cvss_score'], Severity::High);
    changeIssue($lowered, $run, ['severity'], Severity::Critical);

    notify($run);

    Notification::assertSentToTimes($user, WatchedIssuesUpdatedNotification::class, 1);
    Notification::assertSentTo($user, WatchedIssuesUpdatedNotification::class, function ($n) use ($raised, $lowered) {
        $byId = collect($n->items)->keyBy(fn ($i) => $i['issue']->id);

        return $byId[$raised->id]['previous'] === Severity::High && $byId[$raised->id]['issue']->severity === Severity::Critical
            && $byId[$lowered->id]['previous'] === Severity::Critical;
    });
});

it('only tells watchers about changes to old Critical issues', function () {
    $subscriber = subscriber($this->php, Severity::Low, SubscriptionFrequency::Instant);
    $watcher = User::factory()->create();
    $issue = newIssue($this->php, Severity::Critical, ['published_at' => now()->subDays(120)]);
    IssueWatch::create(['user_id' => $watcher->id, 'issue_id' => $issue->id]);
    $run = runWith();
    changeIssue($issue, $run, ['references'], Severity::Critical);

    notify($run);

    Notification::assertNotSentTo($subscriber, WatchedIssuesUpdatedNotification::class);
    Notification::assertSentToTimes($watcher, WatchedIssuesUpdatedNotification::class, 1);
});

it('sends a watcher who also subscribes exactly one update email', function () {
    $user = subscriber($this->php, Severity::Low, SubscriptionFrequency::Instant);
    $issue = newIssue($this->php, Severity::Critical);
    IssueWatch::create(['user_id' => $user->id, 'issue_id' => $issue->id]);
    $run = runWith();
    changeIssue($issue, $run, ['description'], Severity::Critical);

    notify($run);

    Notification::assertSentToTimes($user, WatchedIssuesUpdatedNotification::class, 1);
    Notification::assertSentTo($user, WatchedIssuesUpdatedNotification::class, fn ($n) => count($n->items) === 1 && $n->items[0]['tag'] === 'watched');
});

it('is queued after a sync run with new or changed issues, even a failed one', function () {
    Queue::fake([NotifySubscribers::class]);
    $run = runWith([newIssue($this->php, Severity::High)->id]);
    $run->update(['status' => 'failed']);

    (new class(OsvSource::class, $this->php->id, 'Packagist') extends SyncFeed
    {
        public function fire(SyncRun $run): void
        {
            $this->afterRun($run);
        }
    })->fire($run);

    Queue::assertPushed(NotifySubscribers::class, fn ($j) => $j->runId === $run->id);
});
