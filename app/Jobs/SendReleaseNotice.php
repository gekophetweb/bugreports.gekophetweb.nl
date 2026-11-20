<?php

namespace App\Jobs;

use App\Announcements\AnnouncementNotifier;
use App\Models\Announcement;
use App\Notifications\ReleaseOutNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * The "it's out" email of an upcoming release: fixed versions and the project's own advisories, once.
 * Runs 20 minutes after the first release post and again 3 hours after the window; when nothing is out
 * yet it tries again every 3 hours, for up to 2 days after the window.
 */
class SendReleaseNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $announcementId) {}

    public function handle(): void
    {
        $upcoming = Announcement::query()->with('language')->find($this->announcementId);
        if (! $upcoming || $upcoming->release_notified_at) {
            return;
        }

        $versions = $upcoming->releaseVersions();
        $issues = $upcoming->releaseIssues();

        if ($versions === [] && $issues->isEmpty()) {
            $end = $upcoming->windowEnd() ?? $upcoming->published_at;
            if (now()->lessThan($end->copy()->addDays(2))) {
                self::dispatch($upcoming->id)->delay(now()->addHours(AnnouncementNotifier::FALLBACK_HOURS));
            }

            return;
        }

        // Only the run that sets it sends, when the release post and the fallback overlap.
        $claimed = Announcement::query()->whereKey($upcoming->id)->whereNull('release_notified_at')
            ->update(['release_notified_at' => now()]);
        if ($claimed !== 1) {
            return;
        }

        Notification::send(
            AnnouncementNotifier::recipients($upcoming->language_id),
            new ReleaseOutNotification($upcoming, $versions, $issues->all()),
        );
    }
}
