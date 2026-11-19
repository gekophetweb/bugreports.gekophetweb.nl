<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

trait MailsIssues
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** One-click unsubscribe for mail clients (RFC 8058); the link itself opens a confirmation page. */
    protected function withUnsubscribeHeader(MailMessage $message, ?string $url): MailMessage
    {
        if ($url) {
            $message->withSymfonyMessage(function (Email $email) use ($url) {
                $email->getHeaders()->addTextHeader('List-Unsubscribe', "<$url>");
                $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });
        }

        return $message;
    }
}
