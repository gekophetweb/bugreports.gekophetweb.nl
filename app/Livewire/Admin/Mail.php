<?php

namespace App\Livewire\Admin;

use App\Mail\TestMail;
use App\Support\Mail\MailConfigurator;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail as MailFacade;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Admin → Mail: how the site sends email (SMTP, Resend, Mailgun, Postmark or the log).
 * Secrets are never sent back to the browser; leaving a secret field blank keeps the stored one.
 */
#[Layout('layouts.app')]
#[Title('Mail settings')]
class Mail extends Component
{
    /** Setting key => [property, secret]. */
    private const FIELDS = [
        'mail.mailer' => ['mailer', false],
        'mail.host' => ['host', false],
        'mail.port' => ['port', false],
        'mail.scheme' => ['scheme', false],
        'mail.username' => ['username', false],
        'mail.password' => ['password', true],
        'mail.from_address' => ['from_address', false],
        'mail.from_name' => ['from_name', false],
        'mail.resend_key' => ['resend_key', true],
        'mail.mailgun_domain' => ['mailgun_domain', false],
        'mail.mailgun_secret' => ['mailgun_secret', true],
        'mail.mailgun_endpoint' => ['mailgun_endpoint', false],
        'mail.postmark_token' => ['postmark_token', true],
    ];

    public string $mailer = 'log';

    public string $host = '';

    public string $port = '587';

    public string $scheme = '';

    public string $username = '';

    public string $password = '';

    public string $from_address = '';

    public string $from_name = '';

    public string $resend_key = '';

    public string $mailgun_domain = '';

    public string $mailgun_secret = '';

    public string $mailgun_endpoint = 'api.mailgun.net';

    public string $postmark_token = '';

    /** @var array<string, bool> which secrets are already stored */
    public array $stored = [];

    public function mount(): void
    {
        $this->from_address = (string) config('mail.from.address');
        $this->from_name = (string) config('mail.from.name');

        foreach (self::FIELDS as $key => [$property, $secret]) {
            $value = Settings::get($key);
            if ($secret) {
                $this->stored[$property] = filled($value);
            } elseif (filled($value)) {
                $this->{$property} = (string) $value;
            }
        }
    }

    protected function rules(): array
    {
        $needs = fn (string $property, string $mailer) => $this->mailer === $mailer && ! ($this->stored[$property] ?? false)
            ? 'required' : 'nullable';

        return [
            'mailer' => ['required', Rule::in(array_keys(MailConfigurator::MAILERS))],
            'host' => [$this->mailer === 'smtp' ? 'required' : 'nullable', 'string', 'max:255'],
            'port' => [$this->mailer === 'smtp' ? 'required' : 'nullable', 'integer', 'between:1,65535'],
            'scheme' => ['nullable', Rule::in(['', 'smtp', 'smtps'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'from_address' => ['nullable', 'email:rfc', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'resend_key' => [$needs('resend_key', 'resend'), 'string', 'max:1000'],
            'mailgun_domain' => [$this->mailer === 'mailgun' ? 'required' : 'nullable', 'string', 'max:255'],
            'mailgun_secret' => [$needs('mailgun_secret', 'mailgun'), 'string', 'max:1000'],
            'mailgun_endpoint' => ['nullable', Rule::in(['api.mailgun.net', 'api.eu.mailgun.net'])],
            'postmark_token' => [$needs('postmark_token', 'postmark'), 'string', 'max:1000'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        foreach (self::FIELDS as $key => [$property, $secret]) {
            $value = trim((string) $this->{$property});
            if ($secret) {
                if ($value !== '') {
                    Settings::set($key, $value, secret: true);
                    $this->stored[$property] = true;
                }
                $this->{$property} = '';
            } else {
                Settings::set($key, $value === '' ? null : $value);
            }
        }

        app(MailConfigurator::class)->apply();
        session()->flash('status', 'Mail settings saved.');
    }

    public function sendTest(): void
    {
        try {
            MailFacade::to(auth()->user()->email)->send(new TestMail);
            session()->flash('status', 'Test email sent to '.auth()->user()->email.'.');
        } catch (Throwable $e) {
            $this->addError('test', 'Sending failed: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.mail', ['mailers' => MailConfigurator::MAILERS]);
    }
}
