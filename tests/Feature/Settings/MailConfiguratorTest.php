<?php

use App\Support\Mail\MailConfigurator;
use App\Support\Settings;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;

it('applies smtp settings', function () {
    Settings::set('mail.mailer', 'smtp');
    Settings::set('mail.host', 'smtp.example.test');
    Settings::set('mail.port', '2525');
    Settings::set('mail.username', 'me');
    Settings::set('mail.password', 'pw', secret: true);
    Settings::set('mail.from_address', 'bugs@example.test');
    Settings::set('mail.from_name', 'Bugs');

    app(MailConfigurator::class)->apply();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.smtp.username'))->toBe('me')
        ->and(config('mail.mailers.smtp.password'))->toBe('pw')
        ->and(config('mail.from.address'))->toBe('bugs@example.test')
        ->and(config('mail.from.name'))->toBe('Bugs');
});

it('applies resend, mailgun and postmark keys', function () {
    Settings::set('mail.mailer', 'mailgun');
    Settings::set('mail.resend_key', 're_123', secret: true);
    Settings::set('mail.mailgun_domain', 'mg.example.test');
    Settings::set('mail.mailgun_secret', 'key-1', secret: true);
    Settings::set('mail.mailgun_endpoint', 'api.eu.mailgun.net');
    Settings::set('mail.postmark_token', 'pm-1', secret: true);

    app(MailConfigurator::class)->apply();

    expect(config('mail.default'))->toBe('mailgun')
        ->and(config('services.resend.key'))->toBe('re_123')
        ->and(config('services.mailgun.domain'))->toBe('mg.example.test')
        ->and(config('services.mailgun.secret'))->toBe('key-1')
        ->and(config('services.mailgun.endpoint'))->toBe('api.eu.mailgun.net')
        ->and(config('services.postmark.key'))->toBe('pm-1')
        ->and(config('mail.mailers.mailgun.transport'))->toBe('mailgun');
});

it('leaves the env mailer alone when nothing is stored', function () {
    config(['mail.default' => 'array']);

    app(MailConfigurator::class)->apply();

    expect(config('mail.default'))->toBe('array');
});

it('reapplies before each queued job', function () {
    Settings::set('mail.mailer', 'log');
    app(MailConfigurator::class)->apply();
    Settings::set('mail.mailer', 'array');

    $job = Mockery::mock(Job::class);
    $job->allows('payload')->andReturn([]);
    Event::dispatch(new JobProcessing('redis', $job));

    expect(config('mail.default'))->toBe('array');
});
