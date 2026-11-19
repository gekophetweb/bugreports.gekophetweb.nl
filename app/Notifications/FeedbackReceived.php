<?php

namespace App\Notifications;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the submitter (with their status link), or to a superadmin (with the admin link). */
class FeedbackReceived extends Notification implements ShouldQueue
{
    use Concerns\QueuesOnMail, Queueable;

    public function __construct(public Feedback $feedback, public bool $forAdmin = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->forAdmin) {
            return (new MailMessage)
                ->subject('New feedback: '.str($this->feedback->message)->limit(60))
                ->line("{$this->feedback->name} ({$this->feedback->email}) wrote: {$this->feedback->type->label()}.")
                ->line(str($this->feedback->message)->limit(1000)->toString())
                ->action('Open feedback', route('admin.feedback'));
        }

        return (new MailMessage)
            ->subject('We received your feedback')
            ->line('Thanks for your feedback. You can follow what happens with it here:')
            ->action('See the status', $this->feedback->statusUrl())
            ->line('We email you when the status changes.');
    }
}
