<?php

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Models\Issue;
use App\Models\IssueWatch;
use App\Models\Language;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->sub = Subscription::create(['user_id' => $this->user->id, 'language_id' => Language::factory()->create(['name' => 'PHP'])->id, 'min_severity' => Severity::High, 'frequency' => SubscriptionFrequency::Weekly]);
    $this->other = Subscription::create(['user_id' => $this->user->id, 'language_id' => Language::factory()->create()->id, 'min_severity' => Severity::High, 'frequency' => SubscriptionFrequency::Weekly]);
    $this->watch = IssueWatch::create(['user_id' => $this->user->id, 'issue_id' => Issue::factory()->create()->id]);
});

it('refuses unsigned links', function () {
    $this->get('/unsubscribe/s/'.$this->sub->id)->assertForbidden();
    $this->post('/unsubscribe/s/'.$this->sub->id)->assertForbidden();
});

it('confirms before removing a subscription and removes only that one', function () {
    $url = $this->sub->unsubscribeUrl();

    $this->get($url)->assertOk()->assertSee('PHP');
    expect(Subscription::count())->toBe(2);

    $this->post($url)->assertOk()->assertSee('unsubscribed');
    expect(Subscription::pluck('id')->all())->toBe([$this->other->id]);
});

it('stops watching from a signed link', function () {
    $this->post($this->watch->unwatchUrl())->assertOk();

    expect(IssueWatch::count())->toBe(0);
});

it('is fine with a link that was already used', function () {
    $url = $this->sub->unsubscribeUrl();
    $this->post($url);

    $this->post($url)->assertOk();
    $this->get($url)->assertOk();
});
