<?php

namespace App\Jobs;

use App\Models\Language;
use App\Models\SyncRun;
use App\Sources\Contracts\IssueSource;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Syncs one feed of one source for one language and logs it as a SyncRun. A failure marks the run
 * failed and is not rethrown, so the other feeds keep syncing and the cursor stays where it was.
 */
class SyncFeed implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /** A worker killed mid-run must not keep the feed locked forever. */
    public int $uniqueFor = 1800;

    /** @param  class-string<IssueSource>  $sourceClass */
    public function __construct(public string $sourceClass, public int $languageId, public string $feed) {}

    public function uniqueId(): string
    {
        return $this->sourceClass.'|'.$this->feed;
    }

    public function handle(): void
    {
        $source = app($this->sourceClass);
        $language = Language::query()->findOrFail($this->languageId);

        // Runs whose worker died (timeout, restart) would otherwise show as running forever.
        SyncRun::query()->where('status', 'running')->where('started_at', '<', now()->subSeconds($this->timeout))
            ->update(['status' => 'failed', 'error' => 'Interrupted: the worker stopped before this run finished.', 'finished_at' => now()]);

        $run = SyncRun::query()->create([
            'source' => $source->name(),
            'feed' => $this->feed,
            'language_id' => $language->id,
            'status' => 'running',
            'started_at' => now(),
            'new_issue_ids' => [],
            'changed_issues' => [],
        ]);

        try {
            $source->sync($language, $this->feed, $run);
            $run->status = 'ok';
        } catch (Throwable $e) {
            $run->status = 'failed';
            $run->error = mb_substr($e->getMessage(), 0, 2000);
            Log::warning("Sync {$run->source}/{$this->feed} failed: {$e->getMessage()}");
        }

        $run->finished_at = now();
        $run->save();

        $this->afterRun($run);
    }

    /**
     * Emails about new and changed issues. Also after a failed run: issues it stored before failing
     * are not "new" on the next run, so they would otherwise never be announced.
     */
    protected function afterRun(SyncRun $run): void
    {
        if (($run->new_issue_ids ?? []) !== [] || ($run->changed_issues ?? []) !== []) {
            NotifySubscribers::dispatch($run->id);
        }
    }
}
