<?php

namespace App\Notifications;

use App\Models\LoginCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginCodeNotification extends Notification implements ShouldQueue
{
    use Concerns\QueuesOnMail, Queueable;

    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in code: '.$this->code)
            ->line('Use this code to sign in to '.config('app.name').':')
            ->line('**'.$this->code.'**')
            ->line('It is valid for '.LoginCode::TTL_MINUTES.' minutes. If you did not ask for it, you can ignore this email.');
    }
}
