<?php

use App\Livewire\Issues\Index;
use App\Livewire\Issues\StarToggle;
use App\Livewire\Issues\WatchToggle;
use App\Models\Issue;
use App\Models\IssueStar;
use App\Models\IssueWatch;
use App\Models\Language;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->php = Language::factory()->create(['slug' => 'php']);
    $this->user = User::factory()->create();
    $this->old = Issue::factory()->create(['external_id' => 'OLD', 'published_at' => now()->subDays(10)]);
    $this->new = Issue::factory()->create(['external_id' => 'NEW', 'published_at' => now()->subDay()]);
    $this->old->languages()->attach($this->php);
    $this->new->languages()->attach($this->php);
});

it('puts starred issues first for that user only', function () {
    IssueStar::create(['user_id' => $this->user->id, 'issue_id' => $this->old->id]);

    $this->actingAs($this->user);
    expect(Livewire::test(Index::class)->viewData('issues')->pluck('external_id')->all())->toBe(['OLD', 'NEW']);

    $this->actingAs(User::factory()->create());
    expect(Livewire::test(Index::class)->viewData('issues')->pluck('external_id')->all())->toBe(['NEW', 'OLD']);
});

it('keeps filters over stars', function () {
    $go = Language::factory()->create(['slug' => 'go']);
    $goIssue = Issue::factory()->create(['external_id' => 'GO']);
    $goIssue->languages()->attach($go);
    IssueStar::create(['user_id' => $this->user->id, 'issue_id' => $goIssue->id]);

    $this->actingAs($this->user);

    expect(Livewire::withQueryParams(['languages' => ['php']])->test(Index::class)->viewData('issues')->pluck('external_id')->all())
        ->toBe(['NEW', 'OLD']);
});

it('stars and unstars', function () {
    $this->actingAs($this->user);

    Livewire::test(StarToggle::class, ['issue' => $this->old])->call('toggle')->assertSet('starred', true);
    expect($this->user->hasStarred($this->old))->toBeTrue();

    Livewire::test(StarToggle::class, ['issue' => $this->old])->call('toggle')->assertSet('starred', false);
    expect(IssueStar::count())->toBe(0);
});

it('watches and unwatches', function () {
    $this->actingAs($this->user);

    Livewire::test(WatchToggle::class, ['issue' => $this->old])->call('toggle')->assertSet('watching', true);
    expect($this->user->isWatching($this->old))->toBeTrue();

    Livewire::test(WatchToggle::class, ['issue' => $this->old])->call('toggle');
    expect(IssueWatch::count())->toBe(0);
});

it('sends guests to sign in and back', function () {
    Livewire::test(StarToggle::class, ['issue' => $this->old])->call('toggle')->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route('issues.show', $this->old))->and(IssueStar::count())->toBe(0);

    Livewire::test(WatchToggle::class, ['issue' => $this->old])->call('toggle')->assertRedirect(route('login'));
});

it('shows the toggles on the issue page', function () {
    $this->actingAs($this->user)->get(route('issues.show', $this->old))->assertOk()->assertSee('Star')->assertSee('Watch');
});

it('lists 25 starred issues with a constant number of queries', function () {
    foreach (Issue::factory()->count(25)->create() as $issue) {
        $issue->languages()->attach($this->php);
        IssueStar::create(['user_id' => $this->user->id, 'issue_id' => $issue->id]);
    }
    $this->actingAs($this->user);

    DB::enableQueryLog();
    $this->get('/')->assertOk();
    $queries = count(DB::getQueryLog());

    expect($queries)->toBeLessThanOrEqual(8);
});
