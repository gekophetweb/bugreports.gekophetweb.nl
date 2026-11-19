<?php

namespace App\Notifications;

use App\Notifications\Concerns\MailsIssues;
use App\Support\Digest\DigestBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Issues that changed in one sync run: ones the user watches, and Critical ones in their languages. */
class WatchedIssuesUpdatedNotification extends Notification implements ShouldQueue
{
    use MailsIssues, Queueable;

    /** @param  list<array{issue: \App\Models\Issue, fields: list<string>, previous: \App\Enums\Severity, tag: string, unsubscribeUrl: string}>  $items */
    public function __construct(public array $items) {}

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->items);
        $first = $this->items[0]['issue'];
        $subject = $count === 1 ? "Updated: {$first->external_id} {$first->title}" : "$count issues you follow were updated";

        return $this->withUnsubscribeHeader(
            (new MailMessage)->subject(str($subject)->limit(150)->toString())->markdown('mail.issues', [
                'intro' => $count === 1 ? 'An issue you follow was updated:' : 'Issues you follow were updated:',
                'digest' => DigestBuilder::build(collect($this->items), collect(), []),
                'subscriptions' => [],
            ]),
            $this->items[0]['unsubscribeUrl'] ?? null,
        );
    }
}
