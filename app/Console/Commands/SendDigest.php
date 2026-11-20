<?php

namespace App\Console\Commands;

use App\Enums\Severity;
use App\Enums\SubscriptionFrequency;
use App\Models\Issue;
use App\Models\IssueChange;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\DigestNotification;
use App\Support\Digest\DigestBuilder;
use App\Support\Issues\HiddenPackages;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendDigest extends Command
{
    protected $signature = 'digest:send {period : daily or weekly}';

    protected $description = 'Email the daily or weekly digest of new issues and watched-issue updates';

    public function handle(): int
    {
        $frequency = match ($this->argument('period')) {
            'daily' => SubscriptionFrequency::Daily,
            'weekly' => SubscriptionFrequency::Weekly,
            default => null,
        };
        if (! $frequency) {
            $this->error('Period must be daily or weekly.');

            return self::FAILURE;
        }

        $days = $frequency === SubscriptionFrequency::Daily ? 1 : 7;
        $since = now()->subDays($days);
        $sent = 0;

        User::query()
            ->whereHas('subscriptions', fn ($q) => $q->where('frequency', $frequency))
            ->with(['subscriptions' => fn ($q) => $q->where('frequency', $frequency)->with('language:id,slug,name')])
            ->chunkById(200, function (Collection $users) use ($frequency, $since, $days, &$sent) {
                foreach ($users as $user) {
                    $digest = DigestBuilder::build(
                        $this->watchedChanges($user, $since),
                        $this->newIssues($user->subscriptions, $since, $days, $user->hiddenPackages()),
                        $this->linkFilters($user->subscriptions, $days),
                        markCriticalAlerted: true,
                    );
                    if (! $digest->isEmpty()) {
                        $user->notify(new DigestNotification($digest, $frequency->value, $user->subscriptions->all()));
                        $sent++;
                    }
                }
            });

        $this->info("Sent $sent {$frequency->value} digest(s).");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Subscription>  $subs
     * @param  list<string>  $hidden  the user's hidden packages
     */
    private function newIssues(Collection $subs, $since, int $days, array $hidden): Collection
    {
        $bySlug = $subs->keyBy('language_id');

        return Issue::query()
            ->whereNull('withdrawn_at')
            ->where('first_seen_at', '>=', $since)
            ->where('published_at', '>=', now()->subDays(max($days, config('issues.announce_days'))))
            ->whereHas('languages', fn ($q) => $q->whereIn('languages.id', $subs->pluck('language_id')))
            ->with('languages:id,slug,name')
            ->get()
            ->filter(fn (Issue $issue) => ! HiddenPackages::hides($hidden, $issue->packages ?? [])
                && $issue->languages->contains(fn ($l) => $bySlug->has($l->id) && $bySlug[$l->id]->covers($issue)))
            ->values();
    }

    private function watchedChanges(User $user, $since): Collection
    {
        $watchedIds = $user->watchedIssues()->pluck('issues.id');
        if ($watchedIds->isEmpty()) {
            return collect();
        }

        return IssueChange::query()->with('issue.languages:id,slug,name')
            ->whereIn('issue_id', $watchedIds)->where('created_at', '>=', $since)->orderBy('id')->get()
            ->groupBy('issue_id')
            ->map(fn (Collection $changes) => [
                'issue' => $changes->first()->issue,
                'fields' => $changes->flatMap(fn ($c) => $c->fields)->unique()->values()->all(),
                'previous' => $changes->first()->previous_severity,
                'tag' => 'watched',
            ])
            ->values();
    }

    /** @param  Collection<int, Subscription>  $subs */
    private function linkFilters(Collection $subs, int $days): array
    {
        $lowest = $subs->map(fn (Subscription $s) => $s->min_severity)->sortBy(fn (Severity $s) => $s->rank())->first();

        return array_filter([
            'languages' => $subs->pluck('language.slug')->filter()->values()->all(),
            'severity' => $lowest && $lowest !== Severity::Unknown ? $lowest->value : null,
            'period' => $days === 1 ? '24h' : '7d',
        ]);
    }
}
