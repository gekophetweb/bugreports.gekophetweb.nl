<?php

namespace App\Support\Mail;

use App\Support\Settings;
use Illuminate\Mail\MailManager;

/**
 * Applies the mail settings from Admin → Mail to the running config. Called at boot and
 * before every queued job, so a long-running queue worker picks up changes without a restart.
 * When no mailer has been chosen yet, the .env mail settings stay in effect.
 */
class MailConfigurator
{
    /** Ways to send that the superadmin can pick. */
    public const MAILERS = [
        'smtp' => 'SMTP server',
        'resend' => 'Resend',
        'mailgun' => 'Mailgun',
        'postmark' => 'Postmark',
        'log' => 'Only write emails to the log',
    ];

    public function __construct(private MailManager $manager) {}

    public function apply(): void
    {
        $s = Settings::many([
            'mail.mailer', 'mail.host', 'mail.port', 'mail.scheme', 'mail.username', 'mail.password',
            'mail.from_address', 'mail.from_name', 'mail.resend_key',
            'mail.mailgun_domain', 'mail.mailgun_secret', 'mail.mailgun_endpoint', 'mail.postmark_token',
        ]);

        if (blank($s['mail.mailer'])) {
            return;
        }

        config(array_filter([
            'mail.default' => $s['mail.mailer'],
            'mail.mailers.smtp.host' => $s['mail.host'],
            'mail.mailers.smtp.port' => filled($s['mail.port']) ? (int) $s['mail.port'] : null,
            'mail.mailers.smtp.scheme' => $s['mail.scheme'],
            'mail.mailers.smtp.username' => $s['mail.username'],
            'mail.mailers.smtp.password' => $s['mail.password'],
            'mail.from.address' => $s['mail.from_address'],
            'mail.from.name' => $s['mail.from_name'],
            'services.resend.key' => $s['mail.resend_key'],
            'services.mailgun.domain' => $s['mail.mailgun_domain'],
            'services.mailgun.secret' => $s['mail.mailgun_secret'],
            'services.mailgun.endpoint' => $s['mail.mailgun_endpoint'],
            'services.postmark.key' => $s['mail.postmark_token'],
            'services.postmark.token' => $s['mail.postmark_token'],
        ], fn ($v) => $v !== null && $v !== ''));

        $this->manager->forgetMailers();
    }
}
