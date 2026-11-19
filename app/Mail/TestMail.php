<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent from Admin → Mail to check the mail settings work. Never queued, so errors show on the page. */
class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>Your mail settings work. This is a test email from '.e(config('app.name')).'.</p>');
    }
}
