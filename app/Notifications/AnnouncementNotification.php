<?php

namespace App\Notifications;

use App\Enums\AnnouncementKind;
use App\Models\Announcement;
use App\Notifications\Concerns\MailsIssues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/** Heads-up for an upcoming security release (with a calendar file), or a security advisory, from php.net. */
class AnnouncementNotification extends Notification implements ShouldQueue
{
    use MailsIssues, Queueable;

    public function __construct(public Announcement $announcement) {}

    public function subject(): string
    {
        $a = $this->announcement;
        $language = $a->language->name;

        if ($a->kind === AnnouncementKind::Upcoming && $a->scheduled_at) {
            return trim(ucfirst((string) $a->severity)." $language security release on ".$a->windowLabel());
        }
        if ($a->kind === AnnouncementKind::Advisory) {
            return "$language security advisory: ".Str::of($a->title)->after(':')->trim()->limit(120);
        }

        return $a->title;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->announcement;
        $language = $a->language->name;
        $stopUrl = URL::signedRoute('unsubscribe.advance-warnings', ['user' => $notifiable->id]);
        $mail = (new MailMessage)->subject($this->subject());

        if ($a->kind === AnnouncementKind::Upcoming && $a->scheduled_at) {
            $mail->line("$language will publish security releases on **{$a->windowLabel()}** ({$a->localWindowLabel()}).")
                ->lineIf((bool) $a->severity, 'The highest severity is **'.ucfirst((string) $a->severity).'**. Details and fixed versions are published with the release.');
        }

        $mail->line('**What php.net says:**');
        foreach (explode("\n", Str::limit($a->body, 2000)) as $paragraph) {
            $mail->line($paragraph);
        }

        if ($a->kind === AnnouncementKind::Upcoming) {
            $mail->line('**Prepare now:**')
                ->line('- Check which PHP versions you run (`php -v`, or your Docker image tags).')
                ->line('- Know where your update comes from: distro packages, Docker images or your host can be hours or days behind php.net.')
                ->line('- Plan a moment to deploy and restart PHP-FPM soon after the release.')
                ->line('We email you again when the release is out, with the fixed versions and what they fix.');
            if ($a->scheduled_at) {
                $mail->attachData($this->calendar(), Str::slug($language).'-security-release.ics', ['mime' => 'text/calendar']);
            }
        }

        $mail->action('Read it on php.net', $a->url)
            ->line("You get this because you follow $language and advance warnings are on. [Stop advance warnings]($stopUrl) · [Email settings](".route('my.settings').')');

        return $this->withUnsubscribeHeader($mail, $stopUrl);
    }

    /** One event for the release window, in UTC. */
    private function calendar(): string
    {
        $a = $this->announcement;
        $format = fn ($time) => $time->copy()->utc()->format('Ymd\THis\Z');
        $escape = fn (string $text) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $text);

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.parse_url(config('app.url'), PHP_URL_HOST).'//security releases//EN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:announcement-'.$a->id.'@'.parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$format(now()),
            'DTSTART:'.$format($a->scheduled_at),
            'DTEND:'.$format($a->scheduled_until ?? $a->scheduled_at->copy()->addMinutes(30)),
            'SUMMARY:'.$escape(trim(ucfirst((string) $a->severity).' '.$a->language->name.' security release')),
            'DESCRIPTION:'.$escape(Str::limit($a->body, 1000)),
            'URL:'.$a->url,
            'END:VEVENT',
            'END:VCALENDAR',
        ])."\r\n";
    }
}
