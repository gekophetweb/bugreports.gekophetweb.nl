<?php

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Livewire\My\Issues as MyIssues;
use App\Models\Issue;
use App\Models\IssueStar;
use App\Models\IssueWatch;
use App\Models\Language;
use App\Models\Subscription;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->php = Language::factory()->create(['slug' => 'php']);
    $this->go = Language::factory()->create(['slug' => 'go']);
    $this->user = User::factory()->create();
    Subscription::create(['user_id' => $this->user->id, 'language_id' => $this->php->id, 'min_severity' => Severity::High, 'frequency' => SubscriptionFrequency::Weekly]);
    $this->actingAs($this->user);
});

function myIssue(Language $language, string $id, Severity $severity = Severity::High, int $daysAgo = 1): Issue
{
    $issue = Issue::factory()->create(['external_id' => $id, 'severity' => $severity, 'published_at' => now()->subDays($daysAgo)]);
    $issue->languages()->attach($language);

    return $issue;
}

function mine(array $params = []): array
{
    return Livewire::withQueryParams($params)->test(MyIssues::class)->viewData('issues')->pluck('external_id')->all();
}

it('shows only issues from subscribed languages at or above the minimum severity', function () {
    myIssue($this->php, 'PHP-HIGH');
    myIssue($this->php, 'PHP-LOW', Severity::Low);
    myIssue($this->go, 'GO-HIGH');

    expect(mine())->toBe(['PHP-HIGH']);
    $this->get('/my')->assertOk()->assertSee('My issues');
});

it('adds watched and starred issues from other languages', function () {
    myIssue($this->php, 'PHP-HIGH', daysAgo: 1);
    $watched = myIssue($this->go, 'GO-WATCHED', daysAgo: 2);
    $starred = myIssue($this->go, 'GO-STARRED', daysAgo: 3);
    IssueWatch::create(['user_id' => $this->user->id, 'issue_id' => $watched->id]);
    IssueStar::create(['user_id' => $this->user->id, 'issue_id' => $starred->id]);

    expect(mine())->toBe(['GO-STARRED', 'PHP-HIGH', 'GO-WATCHED']);
});

it('narrows with the toggles', function () {
    myIssue($this->php, 'PHP-HIGH');
    $watched = myIssue($this->go, 'GO-WATCHED');
    IssueWatch::create(['user_id' => $this->user->id, 'issue_id' => $watched->id]);

    expect(mine(['subscribed' => '0', 'starred' => '0']))->toBe(['GO-WATCHED'])
        ->and(mine(['watched' => '0']))->toBe(['PHP-HIGH']);
});

it('shows nothing when every toggle is off', function () {
    myIssue($this->php, 'PHP-HIGH');

    expect(mine(['subscribed' => '0', 'watched' => '0', 'starred' => '0']))->toBe([]);
});

it('applies the date and severity filters on top', function () {
    myIssue($this->php, 'RECENT-CRIT', Severity::Critical, 1);
    myIssue($this->php, 'RECENT-HIGH', Severity::High, 1);
    myIssue($this->php, 'OLD-CRIT', Severity::Critical, 20);

    expect(mine(['severity' => 'critical', 'period' => '7d']))->toBe(['RECENT-CRIT']);
});

it('explains what to do with nothing followed', function () {
    Subscription::query()->delete();

    $this->get('/my')->assertOk()->assertSee('You are not following anything yet')->assertSee(route('my.settings'));
});

it('sends guests to sign in', function () {
    auth()->logout();

    $this->get('/my')->assertRedirect(route('login'));
});
