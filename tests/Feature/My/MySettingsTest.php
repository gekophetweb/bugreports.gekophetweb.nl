<?php

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Livewire\My\Settings;
use App\Models\Issue;
use App\Models\IssueWatch;
use App\Models\Language;
use App\Models\Subscription;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->php = Language::factory()->create(['slug' => 'php', 'name' => 'PHP']);
    $this->user = User::factory()->create(['name' => 'Old Name']);
    $this->actingAs($this->user);
});

it('shows the settings page', function () {
    $this->get('/my/settings')->assertOk()->assertSee('Email settings');
});

it('adds a subscription', function () {
    Livewire::test(Settings::class)
        ->set('new.language_id', $this->php->id)->set('new.min_severity', 'medium')->set('new.frequency', 'weekly')
        ->call('subscribe')->assertHasNoErrors();

    $sub = Subscription::sole();
    expect($sub->user_id)->toBe($this->user->id)
        ->and($sub->min_severity)->toBe(Severity::Medium)
        ->and($sub->frequency)->toBe(SubscriptionFrequency::Weekly);
});

it('rejects a duplicate language and a disabled one', function () {
    Subscription::create(['user_id' => $this->user->id, 'language_id' => $this->php->id, 'min_severity' => Severity::High, 'frequency' => SubscriptionFrequency::Instant]);
    $off = Language::factory()->create(['enabled' => false]);

    Livewire::test(Settings::class)->set('new.language_id', $this->php->id)->call('subscribe')->assertHasErrors('new.language_id');
    Livewire::test(Settings::class)->set('new.language_id', $off->id)->call('subscribe')->assertHasErrors('new.language_id');
    expect(Subscription::count())->toBe(1);
});

it('changes and removes a subscription', function () {
    $sub = Subscription::create(['user_id' => $this->user->id, 'language_id' => $this->php->id, 'min_severity' => Severity::High, 'frequency' => SubscriptionFrequency::Instant]);

    Livewire::test(Settings::class)->call('updateSubscription', $sub->id, 'low', 'daily');
    expect($sub->fresh()->min_severity)->toBe(Severity::Low)->and($sub->fresh()->frequency)->toBe(SubscriptionFrequency::Daily);

    Livewire::test(Settings::class)->call('unsubscribe', $sub->id);
    expect(Subscription::count())->toBe(0);
});

it('cannot touch someone else\'s subscription', function () {
    $other = Subscription::create(['user_id' => User::factory()->create()->id, 'language_id' => $this->php->id, 'min_severity' => Severity::High, 'frequency' => SubscriptionFrequency::Instant]);

    Livewire::test(Settings::class)->call('unsubscribe', $other->id)->assertNotFound();
    expect(Subscription::count())->toBe(1);
});

it('lists and removes watched issues', function () {
    $issue = Issue::factory()->create(['external_id' => 'GHSA-watc-hedd-xxxx']);
    IssueWatch::create(['user_id' => $this->user->id, 'issue_id' => $issue->id]);

    $this->get('/my/settings')->assertSee('GHSA-watc-hedd-xxxx');
    Livewire::test(Settings::class)->call('unwatch', $issue->id);

    expect(IssueWatch::count())->toBe(0);
});

it('changes the name', function () {
    Livewire::test(Settings::class)->set('name', 'New Name')->call('saveName')->assertHasNoErrors();

    expect($this->user->fresh()->name)->toBe('New Name');
});
