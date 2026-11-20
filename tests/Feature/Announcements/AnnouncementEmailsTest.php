<?php

use App\Announcements\AnnouncementNotifier;
use App\Enums\AnnouncementKind;
use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Jobs\NotifySubscribers;
use App\Jobs\SendReleaseNotice;
use App\Livewire\Issues\Index;
use App\Livewire\My\Settings;
use App\Models\Announcement;
use App\Models\Issue;
use App\Models\Language;
use App\Models\Subscription;
use App\Models\SyncRun;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Notifications\NewIssuesNotification;
use App\Notifications\ReleaseOutNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(now()->parse('2026-11-18 17:15:00', 'UTC'));
    $this->php = Language::factory()->create(['slug' => 'php', 'name' => 'PHP']);
    $this->go = Language::factory()->create(['slug' => 'go', 'name' => 'Go']);
});

function follower(Language $language, SubscriptionFrequency $frequency = SubscriptionFrequency::Weekly, bool $warnings = true): User
{
    $user = User::factory()->create();
    $user->update(['advance_warnings' => $warnings]);
    Subscription::create(['user_id' => $user->id, 'language_id' => $language->id, 'min_severity' => Severity::High, 'frequency' => $frequency]);

    return $user;
}

function announcement(Language $language, AnnouncementKind $kind, array $attributes = []): Announcement
{
    static $n = 0;
    $n++;

    return Announcement::create($attributes + [
        'language_id' => $language->id, 'source' => 'php.net', 'external_id' => "https://www.php.net/archive/2026.php#test-$n",
        'kind' => $kind, 'title' => 'Upcoming Critical PHP Security Release', 'body' => 'The PHP security team announces upcoming security releases.',
        'url' => "https://www.php.net/index.php#test-$n", 'published_at' => now()->subMinutes(5),
        'scheduled_at' => $kind === AnnouncementKind::Upcoming ? now()->parse('2026-11-19 14:00', 'UTC') : null,
        'scheduled_until' => $kind === AnnouncementKind::Upcoming ? now()->parse('2026-11-19 14:30', 'UTC') : null,
        'severity' => $kind === AnnouncementKind::Upcoming ? 'critical' : null,
    ]);
}

function ghsaIssue(Language $language, Severity $severity, string $published, string $title = 'An advisory'): Issue
{
    $issue = Issue::factory()->create(['source' => 'ghsa', 'severity' => $severity, 'title' => $title,
        'cvss_score' => $severity === Severity::Critical ? 9.2 : 6.3,
        'published_at' => now()->parse($published, 'UTC'), 'first_seen_at' => now()->parse($published, 'UTC'), 'packages' => ['php-src']]);
    $issue->languages()->attach($language);

    return $issue;
}

// --- Heads-up ---

it('warns PHP followers with advance warnings on straight away', function () {
    Queue::fake();
    $weekly = follower($this->php);
    $optedOut = follower($this->php, warnings: false);
    $goOnly = follower($this->go);
    $upcoming = announcement($this->php, AnnouncementKind::Upcoming);

    app(AnnouncementNotifier::class)->announced($upcoming);

    Notification::assertSentTo($weekly, AnnouncementNotification::class);
    Notification::assertNotSentTo($optedOut, AnnouncementNotification::class);
    Notification::assertNotSentTo($goOnly, AnnouncementNotification::class);
    expect($upcoming->fresh()->notified_at)->not->toBeNull();
});

it('writes a heads-up with the window, what php.net says, what to prepare and a calendar file', function () {
    $user = follower($this->php);
    $mail = (new AnnouncementNotification(announcement($this->php, AnnouncementKind::Upcoming)))->toMail($user);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Critical PHP security release on Thu 19 Nov, 14:00 UTC')
        ->and($html)->toContain('15:00')                       // Europe/Amsterdam
        ->and($html)->toContain('The PHP security team announces upcoming security releases.')
        ->and($html)->toContain('Prepare now')
        ->and($html)->toContain('Stop advance warnings')
        ->and($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->rawAttachments[0]['data'])->toContain('DTSTART:20261119T140000Z')
        ->and($mail->rawAttachments[0]['data'])->toContain('DTEND:20261119T143000Z');
});

it('does not warn about a release window that has passed or an old post', function () {
    Queue::fake();
    $user = follower($this->php);
    $passed = announcement($this->php, AnnouncementKind::Upcoming, [
        'scheduled_at' => now()->subDay(), 'scheduled_until' => now()->subDay()->addMinutes(30)]);
    $old = announcement($this->php, AnnouncementKind::Advisory, ['published_at' => now()->subDays(5), 'title' => 'Security advisory: old']);

    app(AnnouncementNotifier::class)->announced($passed);
    app(AnnouncementNotifier::class)->announced($old);

    Notification::assertNothingSent();
});

it('mails security advisories but not informational ones', function () {
    Queue::fake();
    $user = follower($this->php);

    app(AnnouncementNotifier::class)->announced(announcement($this->php, AnnouncementKind::Advisory, ['title' => 'Security advisory: Critical RCE, update now']));
    app(AnnouncementNotifier::class)->announced(announcement($this->php, AnnouncementKind::Informational, ['title' => 'Informational advisory: Session upload progress']));

    Notification::assertSentToTimes($user, AnnouncementNotification::class, 1);
    Notification::assertSentTo($user, AnnouncementNotification::class, fn ($n) => $n->toMail($user)->subject === 'PHP security advisory: Critical RCE, update now');
});

// --- "It's out" ---

it('schedules the "it\'s out" email 20 minutes after the first release post, with a fallback after the window', function () {
    Queue::fake();
    $upcoming = announcement($this->php, AnnouncementKind::Upcoming);
    app(AnnouncementNotifier::class)->announced($upcoming);
    Queue::assertPushed(SendReleaseNotice::class, fn ($job) => $job->announcementId === $upcoming->id
        && $job->delay->equalTo(now()->parse('2026-11-19 17:30', 'UTC')));

    $this->travelTo(now()->parse('2026-11-19 14:01', 'UTC'));
    app(AnnouncementNotifier::class)->announced(announcement($this->php, AnnouncementKind::Release, ['title' => 'PHP 8.5.13 Released!', 'version' => '8.5.13']));

    Queue::assertPushed(SendReleaseNotice::class, fn ($job) => $job->announcementId === $upcoming->id
        && $job->delay->equalTo(now()->addMinutes(20)));
});

it('sends "it\'s out" once with the fixed versions and the advisories of the release', function () {
    $user = follower($this->php);
    $optedOut = follower($this->php, warnings: false);
    $upcoming = announcement($this->php, AnnouncementKind::Upcoming);
    $this->travelTo(now()->parse('2026-11-19 14:25', 'UTC'));
    announcement($this->php, AnnouncementKind::Release, ['title' => 'PHP 8.4.28 Released!', 'version' => '8.4.28']);
    announcement($this->php, AnnouncementKind::Release, ['title' => 'PHP 8.5.13 Released!', 'version' => '8.5.13']);
    $critical = ghsaIssue($this->php, Severity::Critical, '2026-11-19 14:06', 'Session serializer mismatch');
    $medium = ghsaIssue($this->php, Severity::Medium, '2026-11-19 14:08', 'SOAP use-after-free');
    ghsaIssue($this->php, Severity::High, '2026-10-21 14:06', 'From an older release');

    SendReleaseNotice::dispatchSync($upcoming->id);
    SendReleaseNotice::dispatchSync($upcoming->id);

    Notification::assertSentToTimes($user, ReleaseOutNotification::class, 1);
    Notification::assertNotSentTo($optedOut, ReleaseOutNotification::class);
    Notification::assertSentTo($user, ReleaseOutNotification::class, function ($n) use ($user, $critical, $medium) {
        $mail = $n->toMail($user);

        return $n->versions === ['8.5.13', '8.4.28']
            && collect($n->issues)->pluck('id')->all() === [$critical->id, $medium->id]
            && $mail->subject === 'PHP security release is out: update to 8.5.13 or 8.4.28'
            && str_contains((string) $mail->render(), 'Session serializer mismatch');
    });
    expect($upcoming->fresh()->release_notified_at)->not->toBeNull();
});

it('tries again later when nothing is out yet', function () {
    Queue::fake();
    $upcoming = announcement($this->php, AnnouncementKind::Upcoming);
    $this->travelTo(now()->parse('2026-11-19 17:30', 'UTC'));

    (new SendReleaseNotice($upcoming->id))->handle();

    Notification::assertNothingSent();
    Queue::assertPushed(SendReleaseNotice::class, fn ($job) => $job->delay->equalTo(now()->addHours(3)));
    expect($upcoming->fresh()->release_notified_at)->toBeNull();
});

it('holds the release advisories back from the normal alert for users who get "it\'s out"', function () {
    $warned = follower($this->php, SubscriptionFrequency::Instant);
    $notWarned = follower($this->php, SubscriptionFrequency::Instant, warnings: false);
    announcement($this->php, AnnouncementKind::Upcoming);
    $this->travelTo(now()->parse('2026-11-19 14:10', 'UTC'));
    $advisory = ghsaIssue($this->php, Severity::Critical, '2026-11-19 14:06');
    $run = SyncRun::create(['source' => 'ghsa', 'feed' => 'php/php-src', 'status' => 'ok', 'started_at' => now(), 'new_issue_ids' => [$advisory->id], 'changed_issues' => []]);

    NotifySubscribers::dispatchSync($run->id);

    Notification::assertNotSentTo($warned, NewIssuesNotification::class);
    Notification::assertSentTo($notWarned, NewIssuesNotification::class);
});

// --- Settings and links ---

it('switches advance warnings off in email settings and through the signed link', function () {
    $user = follower($this->php);

    Livewire::actingAs($user)->test(Settings::class)->set('advanceWarnings', false);
    expect($user->fresh()->advance_warnings)->toBeFalse();

    $user->update(['advance_warnings' => true]);
    $this->post(URL::signedRoute('unsubscribe.advance-warnings', ['user' => $user->id]))->assertOk()->assertSee('advance warnings');
    expect($user->fresh()->advance_warnings)->toBeFalse();
});

// --- Banner ---

it('shows the upcoming release on the issue list until a few days after it', function () {
    announcement($this->php, AnnouncementKind::Upcoming);

    $this->get('/')->assertSee('Critical PHP security release expected')->assertSee('Thu 19 Nov, 14:00 UTC');
    Livewire::withQueryParams(['languages' => ['go']])->test(Index::class)->assertDontSee('PHP security release expected');

    $this->travelTo(now()->parse('2026-11-19 15:00', 'UTC'));
    $this->get('/')->assertSee('PHP security release is out');

    $this->travelTo(now()->parse('2026-11-23 15:00', 'UTC'));
    $this->get('/')->assertDontSee('PHP security release');
});
