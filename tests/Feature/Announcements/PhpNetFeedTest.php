<?php

use App\Announcements\PhpNetFeed;
use App\Enums\AnnouncementKind;
use App\Models\Announcement;
use App\Models\Language;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Notification::fake();
    Queue::fake();
    $this->travelTo(now()->parse('2026-11-20 12:00:00', 'UTC'));
    $this->php = Language::factory()->create(['slug' => 'php', 'name' => 'PHP']);
});

function phpFeed(): string
{
    return file_get_contents(base_path('tests/Fixtures/phpnet/feed.atom'));
}

it('classifies php.net posts and skips the rest', function () {
    $items = collect(PhpNetFeed::parse(phpFeed()))->keyBy('external_id');

    expect($items)->toHaveCount(6)
        ->and($items->pluck('kind')->map->value->countBy()->all())
        ->toBe(['release' => 2, 'upcoming' => 2, 'informational' => 1, 'advisory' => 1]);

    $release = $items['https://www.php.net/archive/2026.php#2026-11-19-4'];
    expect($release['kind'])->toBe(AnnouncementKind::Release)
        ->and($release['version'])->toBe('8.5.13')
        ->and($release['url'])->toBe('https://www.php.net/index.php#2026-11-19-4');
});

it('reads the release window and severity of an upcoming release', function () {
    $items = collect(PhpNetFeed::parse(phpFeed()))->where('kind', AnnouncementKind::Upcoming)->keyBy(fn ($i) => $i['published_at']->toDateString());

    $november = $items['2026-11-18'];
    expect($november['scheduled_at']->toIso8601ZuluString())->toBe('2026-11-19T14:00:00Z')
        ->and($november['scheduled_until']->toIso8601ZuluString())->toBe('2026-11-19T14:30:00Z')
        ->and($november['severity'])->toBe('critical')
        ->and($november['body'])->toContain('require either non-default configuration');

    $october = $items['2026-10-20'];
    expect($october['scheduled_at']->toIso8601ZuluString())->toBe('2026-10-21T14:00:00Z')
        ->and($october['scheduled_until']->toIso8601ZuluString())->toBe('2026-10-21T14:30:00Z')
        ->and($october['severity'])->toBe('critical');
});

it('stores new announcements once and asks php.net only for changes', function () {
    Http::fake(['www.php.net/feed.atom' => Http::sequence()
        ->push(phpFeed(), 200, ['ETag' => '"v1"'])
        ->push('', 304)]);

    expect(app(PhpNetFeed::class)->sync())->toBe(6)
        ->and(app(PhpNetFeed::class)->sync())->toBe(0)
        ->and(Announcement::count())->toBe(6)
        ->and(Announcement::where('kind', 'upcoming')->first()->language_id)->toBe($this->php->id);

    Http::assertSent(fn (Request $r) => $r->hasHeader('If-None-Match', '"v1"'));
});

it('does nothing while PHP is not a synced language', function () {
    $this->php->update(['enabled' => false]);
    Http::fake();

    expect(app(PhpNetFeed::class)->sync())->toBe(0);
    Http::assertNothingSent();
});

it('runs from the scheduler every five minutes', function () {
    Http::fake(['www.php.net/feed.atom' => Http::response(phpFeed())]);

    $this->artisan('announcements:sync')->assertSuccessful();

    expect(Announcement::count())->toBe(6);
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'announcements:sync'));
    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('*/5 * * * *');
});
