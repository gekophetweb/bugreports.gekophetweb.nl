<?php

namespace App\Announcements;

use App\Enums\AnnouncementKind;
use App\Jobs\SendReleaseNotice;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * What happens when a new announcement comes in:
 * - upcoming release: heads-up to followers with advance warnings on, and a fallback "it's out" check
 *   3 hours after the window;
 * - security advisory: emailed to the same people;
 * - security release: the "it's out" email of the upcoming release it belongs to, 20 minutes later,
 *   when the project's advisories have been published too;
 * - informational: only shown on the site.
 * Posts older than 2 days (a first import) are stored without emails.
 */
class AnnouncementNotifier
{
    public const RECENT_DAYS = 2;

    public const RELEASE_DELAY_MINUTES = 20;

    public const FALLBACK_HOURS = 3;

    public function announced(Announcement $announcement): void
    {
        if ($announcement->published_at->lessThan(now()->subDays(self::RECENT_DAYS))) {
            return;
        }

        match ($announcement->kind) {
            AnnouncementKind::Upcoming => $this->upcoming($announcement),
            AnnouncementKind::Advisory => $this->send($announcement),
            AnnouncementKind::Release => $this->released($announcement),
            AnnouncementKind::Informational => null,
        };
    }

    private function upcoming(Announcement $announcement): void
    {
        $end = $announcement->windowEnd();
        if ($end && $end->isPast()) {
            return; // already out: nothing to prepare for any more
        }

        $this->send($announcement);
        if ($end) {
            SendReleaseNotice::dispatch($announcement->id)->delay($end->copy()->addHours(self::FALLBACK_HOURS));
        }
    }

    private function released(Announcement $release): void
    {
        $upcoming = Announcement::query()->where('language_id', $release->language_id)
            ->where('kind', AnnouncementKind::Upcoming)->whereNull('release_notified_at')
            ->whereBetween('scheduled_at', [$release->published_at->copy()->subDays(2), $release->published_at->copy()->addHours(12)])
            ->latest('scheduled_at')->first();

        if ($upcoming) {
            SendReleaseNotice::dispatch($upcoming->id)->delay(now()->addMinutes(self::RELEASE_DELAY_MINUTES));
        }
    }

    private function send(Announcement $announcement): void
    {
        Notification::send(self::recipients($announcement->language_id), new AnnouncementNotification($announcement));
        $announcement->update(['notified_at' => now()]);
    }

    /** @return Collection<int, User> users following the language with advance warnings on */
    public static function recipients(int $languageId): Collection
    {
        return User::query()->where('advance_warnings', true)
            ->whereHas('subscriptions', fn ($q) => $q->where('language_id', $languageId))
            ->get();
    }
}
