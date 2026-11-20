<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Models\Issue;
use App\Notifications\Concerns\MailsIssues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** The release php.net warned about is out: the fixed versions and the project's own advisories. */
class ReleaseOutNotification extends Notification implements ShouldQueue
{
    use MailsIssues, Queueable;

    /**
     * @param  list<string>  $versions  newest first
     * @param  list<Issue>  $issues  most severe first
     */
    public function __construct(public Announcement $upcoming, public array $versions, public array $issues) {}

    public function subject(): string
    {
        $language = $this->upcoming->language->name;
        if ($this->versions === []) {
            return "$language security release is out";
        }
        $last = $this->versions[count($this->versions) - 1];
        $list = count($this->versions) > 1 ? implode(', ', array_slice($this->versions, 0, -1))." or $last" : $last;

        return "$language security release is out: update to $list";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $language = $this->upcoming->language;
        $stopUrl = URL::signedRoute('unsubscribe.advance-warnings', ['user' => $notifiable->id]);
        $mail = (new MailMessage)->subject($this->subject())
            ->line("The $language->name security release announced for {$this->upcoming->windowLabel()} is out.");

        if ($this->versions !== []) {
            $mail->line('**Fixed versions:** '.implode(' · ', $this->versions));
        }
        if ($this->issues !== []) {
            $mail->line('**Fixed in this release:**');
            foreach ($this->issues as $issue) {
                $score = $issue->cvss_score !== null ? ' '.number_format($issue->cvss_score, 1) : '';
                $mail->line("- **{$issue->severity->label()}$score** · [{$issue->title}](".route('issues.show', $issue).')');
            }
        } else {
            $mail->line("$language->name's own advisories are not published yet; they will show up on the site.");
        }

        $mail->action('See them on the site', route('home', ['languages' => [$language->slug], 'period' => '7d']))
            ->line("You get this because you follow $language->name and advance warnings are on. [Stop advance warnings]($stopUrl) · [Email settings](".route('my.settings').')');

        return $this->withUnsubscribeHeader($mail, $stopUrl);
    }
}
