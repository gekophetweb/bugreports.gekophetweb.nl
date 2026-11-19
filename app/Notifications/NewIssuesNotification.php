<?php

namespace App\Notifications;

use App\Enums\Severity;
use App\Models\Issue;
use App\Models\Subscription;
use App\Notifications\Concerns\MailsIssues;
use App\Support\Digest\DigestBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** New issues from one sync run for one user: instant subscriptions plus Critical alerts. */
class NewIssuesNotification extends Notification implements ShouldQueue
{
    use MailsIssues, Queueable;

    /**
     * @param  list<Issue>  $issues
     * @param  list<Subscription>  $subscriptions  the user's subscriptions for these languages (unsubscribe links)
     */
    public function __construct(public array $issues, public array $subscriptions) {}

    public function subject(): string
    {
        $count = count($this->issues);
        $critical = collect($this->issues)->filter(fn (Issue $i) => $i->severity === Severity::Critical)->count();
        $languages = collect($this->issues)->flatMap(fn (Issue $i) => $i->languages->pluck('name'))->unique()->take(3)->implode(', ');

        return $critical > 0
            ? "Critical: $critical new critical ".str('issue')->plural($critical)." in $languages"
            : "$count new ".str('issue')->plural($count)." in $languages";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $digest = DigestBuilder::build(collect(), collect($this->issues), ['period' => '24h']);

        return $this->withUnsubscribeHeader(
            (new MailMessage)->subject($this->subject())->markdown('mail.issues', [
                'intro' => 'New issues matching your subscriptions:',
                'digest' => $digest,
                'subscriptions' => $this->subscriptions,
            ]),
            ($this->subscriptions[0] ?? null)?->unsubscribeUrl(),
        );
    }
}
