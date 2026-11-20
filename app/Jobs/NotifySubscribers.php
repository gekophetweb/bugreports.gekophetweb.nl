<?php

namespace App\Jobs;

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Models\Announcement;
use App\Models\Issue;
use App\Models\IssueChange;
use App\Models\IssueWatch;
use App\Models\Subscription;
use App\Models\SyncRun;
use App\Models\User;
use App\Notifications\NewIssuesNotification;
use App\Notifications\WatchedIssuesUpdatedNotification;
use App\Support\Issues\PackageVisibility;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * After a sync run: at most one "new issues" email and one "issues updated" email per user.
 *
 * New issues go to instant subscribers (at or above their minimum severity) and, when Critical, to
 * daily and weekly subscribers too. Updates go to watchers and, for recent Critical issues (or ones
 * that just became or stopped being Critical), to every subscriber of the issue's languages.
 */
class NotifySubscribers implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        $run = SyncRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        $this->newIssues($run->new_issue_ids ?? []);
        $this->updates($run, $run->changed_issues ?? []);
    }

    /** @param  list<int>  $ids */
    private function newIssues(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $issues = Issue::query()->whereIn('id', $ids)->announceable()->with('languages:id,slug,name')->get();
        if ($issues->isEmpty()) {
            return;
        }

        // Advisories of a release php.net warned about go out in its "it's out" email instead.
        $held = Announcement::heldIssueIds($issues);

        $this->subscriptionsFor($issues)->each(function (Collection $subs) use ($issues, $held) {
            $user = $subs->first()->user;
            $matched = $issues->filter(fn (Issue $issue) => ! ($user->advance_warnings && in_array($issue->id, $held, true))
                && $subs->contains(fn (Subscription $s) => $issue->languages->contains('id', $s->language_id)
                && PackageVisibility::emailable($issue, $user, $s)
                && ($s->frequency === SubscriptionFrequency::Instant ? $s->covers($issue) : $issue->severity === Severity::Critical)));

            if ($matched->isNotEmpty()) {
                $subs->first()->user->notify(new NewIssuesNotification($matched->values()->all(), $subs->values()->all()));
            }
        });
    }

    /** @param  array<string, list<string>>  $changed */
    private function updates(SyncRun $run, array $changed): void
    {
        if ($changed === []) {
            return;
        }
        $issues = Issue::query()->whereIn('id', array_keys($changed))->with('languages:id,slug,name')->get()->keyBy('id');
        $previous = IssueChange::query()->where('sync_run_id', $run->id)->orderBy('id')->get()
            ->groupBy('issue_id')->map(fn ($changes) => $changes->first()->previous_severity);

        /** @var array<int, array<int, array>> $perUser user id => issue id => item */
        $perUser = [];
        $item = fn (Issue $issue, string $tag, string $unsubscribeUrl) => [
            'issue' => $issue,
            'fields' => $changed[(string) $issue->id] ?? [],
            'previous' => $previous[$issue->id] ?? $issue->severity,
            'tag' => $tag,
            'unsubscribeUrl' => $unsubscribeUrl,
        ];

        foreach (IssueWatch::query()->whereIn('issue_id', $issues->keys())->get() as $watch) {
            $perUser[$watch->user_id][$watch->issue_id] = $item($issues[$watch->issue_id], 'watched', $watch->unwatchUrl());
        }

        $critical = $issues->filter(fn (Issue $issue) => $issue->isCriticalUpdateRelevant($previous[$issue->id] ?? $issue->severity));
        foreach ($critical as $issue) {
            $subs = Subscription::query()->with('user')->whereIn('language_id', $issue->languages->pluck('id'))->get();
            foreach ($subs as $sub) {
                // Watched issues are always mailed; a Critical update only for packages the subscription covers.
                if (! $sub->user || ! PackageVisibility::emailable($issue, $sub->user, $sub)) {
                    continue;
                }
                $perUser[$sub->user_id][$issue->id] ??= $item($issue, 'critical', $sub->unsubscribeUrl());
            }
        }

        $users = User::query()->whereIn('id', array_keys($perUser))->get()->keyBy('id');
        foreach ($perUser as $userId => $items) {
            $users[$userId]?->notify(new WatchedIssuesUpdatedNotification(array_values($items)));
        }
    }

    /** @return Collection<int, Collection<int, Subscription>> subscriptions for these issues' languages, by user */
    private function subscriptionsFor(Collection $issues): Collection
    {
        $languageIds = $issues->flatMap(fn (Issue $i) => $i->languages->pluck('id'))->unique()->values();

        return Subscription::query()->with(['user', 'language:id,slug,name'])->whereIn('language_id', $languageIds)->get()->groupBy('user_id');
    }
}
