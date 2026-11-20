<?php

namespace App\Livewire\My;

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Models\IssueWatch;
use App\Models\Language;
use App\Models\Subscription;
use App\Support\Issues\HiddenPackages;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** My account → Email settings: language subscriptions, watched issues, name. */
#[Layout('layouts.app')]
#[Title('Email settings')]
class Settings extends Component
{
    /** @var array{language_id: int|string, min_severity: string, frequency: string} */
    public array $new = ['language_id' => '', 'min_severity' => 'high', 'frequency' => 'instant'];

    public string $name = '';

    public string $newHidden = '';

    public string $newUsed = '';

    public bool $advanceWarnings = true;

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->advanceWarnings = (bool) auth()->user()->advance_warnings;
    }

    public function updatedAdvanceWarnings(bool $value): void
    {
        auth()->user()->update(['advance_warnings' => $value]);
    }

    public function subscribe(): void
    {
        $userId = auth()->id();
        $this->validate([
            'new.language_id' => ['required', 'integer',
                Rule::exists('languages', 'id')->where('enabled', true),
                Rule::unique('subscriptions', 'language_id')->where('user_id', $userId)],
            'new.min_severity' => ['required', Rule::in(self::severityValues())],
            'new.frequency' => ['required', Rule::enum(SubscriptionFrequency::class)],
        ], [
            'new.language_id.unique' => 'You already follow this one. Change it in the list below.',
            'new.language_id.exists' => 'Pick a language or server from the list.',
        ]);

        Subscription::query()->create([
            'user_id' => $userId,
            'language_id' => (int) $this->new['language_id'],
            'min_severity' => $this->new['min_severity'],
            'frequency' => $this->new['frequency'],
        ]);
        $this->new['language_id'] = '';
        session()->flash('status', 'Subscribed.');
    }

    public function updateSubscription(int $id, string $minSeverity, string $frequency): void
    {
        $sub = $this->own($id);
        abort_unless(in_array($minSeverity, self::severityValues(), true) && SubscriptionFrequency::tryFrom($frequency), 422);
        $sub->update(['min_severity' => $minSeverity, 'frequency' => $frequency]);
    }

    public function unsubscribe(int $id): void
    {
        $this->own($id)->delete();
    }

    public function unwatch(int $issueId): void
    {
        IssueWatch::query()->where(['user_id' => auth()->id(), 'issue_id' => $issueId])->delete();
    }

    public function addUsed(): void
    {
        $pattern = HiddenPackages::normalize($this->newUsed);
        if (! HiddenPackages::isValid($pattern)) {
            $this->addError('newUsed', 'Enter a package name like laravel/framework, or laravel/* for a whole vendor.');

            return;
        }
        auth()->user()->usePackage($pattern);
        $this->newUsed = '';
    }

    public function removeUsed(string $pattern): void
    {
        auth()->user()->unusePackage($pattern);
    }

    /** Whether a subscription covers all packages of its language, or only the language itself and used packages. */
    public function setAllPackages(int $id, bool $all): void
    {
        $this->own($id)->update(['all_packages' => $all]);
    }

    public function addHidden(): void
    {
        $pattern = HiddenPackages::normalize($this->newHidden);
        if (! HiddenPackages::isValid($pattern)) {
            $this->addError('newHidden', 'Enter a package name like silverstripe/framework, or silverstripe/* for a whole vendor.');

            return;
        }
        if (count(auth()->user()->hiddenPackages()) >= HiddenPackages::MAX) {
            $this->addError('newHidden', 'You can hide at most '.HiddenPackages::MAX.' packages. Use vendor/* to hide a whole vendor.');

            return;
        }
        auth()->user()->hidePackage($pattern);
        $this->newHidden = '';
    }

    public function removeHidden(string $pattern): void
    {
        auth()->user()->unhidePackage($pattern);
    }

    public function saveName(): void
    {
        $this->validate(['name' => 'required|string|max:255']);
        auth()->user()->update(['name' => $this->name]);
        session()->flash('status', 'Name saved.');
    }

    private function own(int $id): Subscription
    {
        return Subscription::query()->where('user_id', auth()->id())->findOrFail($id);
    }

    /** @return list<string> */
    private static function severityValues(): array
    {
        return array_map(fn (Severity $s) => $s->value, array_filter(Severity::cases(), fn ($s) => $s !== Severity::Unknown));
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.my.settings', [
            'subscriptions' => $user->subscriptions()->with('language:id,name,slug')->get()->sortBy('language.name'),
            'languages' => Language::query()->enabled()->whereNotIn('id', $user->subscriptions()->pluck('language_id'))->orderBy('name')->get(['id', 'name']),
            'watched' => $user->watchedIssues()->orderByDesc('issue_watches.created_at')->get(['issues.id', 'external_id', 'title', 'severity']),
            'severities' => array_filter(Severity::cases(), fn ($s) => $s !== Severity::Unknown),
            'frequencies' => SubscriptionFrequency::cases(),
            'hidden' => $user->hiddenPackages(),
            'used' => $user->usedPackages(),
        ]);
    }
}
