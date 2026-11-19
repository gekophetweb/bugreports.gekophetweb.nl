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

    /** Hook for notifications (Task 13). */
    protected function afterRun(SyncRun $run): void {}
}
