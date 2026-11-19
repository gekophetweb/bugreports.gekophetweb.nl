<?php

namespace App\Livewire\Concerns;

use App\Models\Issue;
use App\Models\IssueStar;
use App\Models\IssueWatch;

/** Star and watch toggles. Guests are sent to sign in and come back to $returnUrl. */
trait TogglesIssueFlags
{
    /** @return bool|null the new state, or null when the guest was redirected */
    protected function flip(string $model, Issue $issue, string $returnUrl): ?bool
    {
        if (! auth()->check()) {
            session()->put('url.intended', $returnUrl);
            $this->redirectRoute('login');

            return null;
        }

        /** @var class-string<IssueStar|IssueWatch> $model */
        $attributes = ['user_id' => auth()->id(), 'issue_id' => $issue->id];
        if ($model::query()->where($attributes)->delete() > 0) {
            return false;
        }
        $model::query()->firstOrCreate($attributes);

        return true;
    }
}
