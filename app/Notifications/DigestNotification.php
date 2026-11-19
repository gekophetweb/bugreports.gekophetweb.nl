<?php

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Concerns\MailsIssues;
use App\Support\Digest\Digest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The daily or weekly digest. */
class DigestNotification extends Notification implements ShouldQueue
{
    use MailsIssues, Queueable;

    /** @param  list<Subscription>  $subscriptions */
    public function __construct(public Digest $digest, public string $period, public array $subscriptions) {}

    public function toMail(object $notifiable): MailMessage
    {
        $new = $this->digest->newCount();
        $updated = count($this->digest->watched);
        $parts = array_filter([
            $new ? "$new new ".str('issue')->plural($new) : null,
            $updated ? "$updated ".str('update')->plural($updated) : null,
        ]);
        $label = $this->period === 'daily' ? 'Daily' : 'Weekly';

        return $this->withUnsubscribeHeader(
            (new MailMessage)->subject("$label digest: ".implode(', ', $parts))->markdown('mail.issues', [
                'intro' => $this->period === 'daily' ? 'Here is what happened in the last 24 hours.' : 'Here is what happened in the last 7 days.',
                'digest' => $this->digest,
                'subscriptions' => $this->subscriptions,
            ]),
            ($this->subscriptions[0] ?? null)?->unsubscribeUrl(),
        );
    }
}
