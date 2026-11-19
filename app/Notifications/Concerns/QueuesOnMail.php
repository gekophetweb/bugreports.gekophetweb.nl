<?php

namespace App\Notifications\Concerns;

/**
 * Emails go on their own "mail" queue with its own worker (see docker-compose.yml), so sign-in codes
 * and alerts never wait behind long sync jobs on the default queue.
 */
trait QueuesOnMail
{
    /** @return array<string, string> */
    public function viaQueues(): array
    {
        return ['mail' => 'mail'];
    }
}
