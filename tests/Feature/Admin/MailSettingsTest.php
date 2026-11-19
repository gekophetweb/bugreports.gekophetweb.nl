<?php

use App\Enums\Role;
use App\Livewire\Admin\Mail as MailPage;
use App\Mail\TestMail;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Superadmin]);
    $this->actingAs($this->admin);
});

function fillSmtp($component)
{
    return $component->set('mailer', 'smtp')->set('host', 'smtp.example.test')->set('port', '587')
        ->set('scheme', 'smtp')->set('username', 'me')->set('password', 'hunter2')
        ->set('from_address', 'bugs@example.test')->set('from_name', 'Bugs');
}

it('shows the mail page to the superadmin', function () {
    $this->get(route('admin.mail'))->assertOk()->assertSee('Outgoing mail');
});

it('is forbidden for normal users', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.mail'))->assertForbidden();
});

it('saves smtp settings and applies them', function () {
    fillSmtp(Livewire::test(MailPage::class))->call('save')->assertHasNoErrors();

    expect(Settings::get('mail.mailer'))->toBe('smtp')
        ->and(Settings::get('mail.host'))->toBe('smtp.example.test')
        ->and(Settings::get('mail.password'))->toBe('hunter2')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test');
});

it('keeps the stored password when the field is left blank', function () {
    fillSmtp(Livewire::test(MailPage::class))->call('save');

    fillSmtp(Livewire::test(MailPage::class))->set('password', '')->set('host', 'other.example.test')->call('save');

    expect(Settings::get('mail.password'))->toBe('hunter2')->and(Settings::get('mail.host'))->toBe('other.example.test');
});

it('never renders a stored secret', function () {
    fillSmtp(Livewire::test(MailPage::class))->call('save');

    $this->get(route('admin.mail'))->assertDontSee('hunter2');
    Livewire::test(MailPage::class)->assertSet('password', '');
});

it('saves resend, mailgun and postmark keys', function () {
    Livewire::test(MailPage::class)->set('mailer', 'resend')->set('resend_key', 're_1')
        ->set('from_address', 'bugs@example.test')->set('from_name', 'Bugs')->call('save')->assertHasNoErrors();
    expect(Settings::get('mail.resend_key'))->toBe('re_1');

    Livewire::test(MailPage::class)->set('mailer', 'mailgun')->set('mailgun_domain', 'mg.example.test')
        ->set('mailgun_secret', 'key-1')->set('mailgun_endpoint', 'api.eu.mailgun.net')->call('save')->assertHasNoErrors();
    expect(Settings::get('mail.mailgun_secret'))->toBe('key-1');

    Livewire::test(MailPage::class)->set('mailer', 'postmark')->set('postmark_token', 'pm-1')->call('save')->assertHasNoErrors();
    expect(Settings::get('mail.postmark_token'))->toBe('pm-1');
});

it('validates the fields', function () {
    Livewire::test(MailPage::class)->set('mailer', 'smtp')->set('host', '')->set('port', '70000')
        ->set('from_address', 'nope')->call('save')
        ->assertHasErrors(['host', 'port', 'from_address']);

    Livewire::test(MailPage::class)->set('mailer', 'carrier-pigeon')->call('save')->assertHasErrors('mailer');
    Livewire::test(MailPage::class)->set('mailer', 'resend')->set('resend_key', '')->set('from_address', 'a@b.test')
        ->call('save')->assertHasErrors('resend_key');
});

it('sends a test email to the admin', function () {
    Mail::fake();

    Livewire::test(MailPage::class)->call('sendTest')->assertHasNoErrors();

    Mail::assertSent(TestMail::class, fn (TestMail $m) => $m->hasTo($this->admin->email));
});
