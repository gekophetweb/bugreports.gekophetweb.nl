<?php

namespace App\Livewire\My;

use App\Livewire\Issues\Index;
use App\Models\Issue;
use App\Models\IssueStar;
use App\Models\IssueWatch;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * My issues: the issue list limited to the user's subscribed languages (at each subscription's minimum
 * severity), watched issues and starred issues. Every filter of the public list works here too.
 */
#[Title('My issues')]
class Issues extends Index
{
    #[Url(except: true)]
    public bool $subscribed = true;

    #[Url(except: true)]
    public bool $watched = true;

    #[Url(except: true)]
    public bool $starred = true;

    protected function baseQuery()
    {
        $user = auth()->user();
        $subs = Subscription::query()->where('user_id', $user->id)->get();

        return Issue::query()->notWithdrawn()->where(function (Builder $q) use ($user, $subs) {
            $q->whereRaw('false');
            if ($this->subscribed) {
                foreach ($subs as $sub) {
                    $q->orWhere(fn (Builder $q) => $q
                        ->whereHas('languages', fn (Builder $l) => $l->where('languages.id', $sub->language_id))
                        ->minSeverity($sub->min_severity));
                }
            }
            if ($this->watched) {
                $q->orWhereIn('issues.id', IssueWatch::query()->select('issue_id')->where('user_id', $user->id));
            }
            if ($this->starred) {
                $q->orWhereIn('issues.id', IssueStar::query()->select('issue_id')->where('user_id', $user->id));
            }
        });
    }

    protected function extraViewData(): array
    {
        $user = auth()->user();

        return [
            'heading' => 'My issues',
            'myToggles' => true,
            'followsNothing' => ! $user->subscriptions()->exists()
                && ! IssueWatch::query()->where('user_id', $user->id)->exists()
                && ! IssueStar::query()->where('user_id', $user->id)->exists(),
        ];
    }
}
