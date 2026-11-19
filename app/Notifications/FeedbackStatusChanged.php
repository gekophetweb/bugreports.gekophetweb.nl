<?php

namespace App\Notifications;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackStatusChanged extends Notification implements ShouldQueue
{
    use Concerns\QueuesOnMail, Queueable;

    public function __construct(public Feedback $feedback) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your feedback is now: '.$this->feedback->status->label())
            ->line('The status of your feedback changed to **'.$this->feedback->status->label().'**.');

        if (filled($this->feedback->public_note)) {
            $mail->line('Note: '.$this->feedback->public_note);
        }

        return $mail->action('See the status', $this->feedback->statusUrl());
    }
}
