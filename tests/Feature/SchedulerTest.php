<?php

use App\Jobs\SyncFeed;
use App\Models\Language;
use App\Models\SyncRun;
use App\Sources\Nvd\NvdSource;
use App\Sources\Osv\OsvSource;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

function scheduled(string $command): ?Event
{
    return collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', $command));
}

it('schedules osv every five and nvd every ten minutes', function () {
    expect(scheduled('issues:sync osv')->expression)->toBe('*/5 * * * *')
        ->and(scheduled('issues:sync nvd')->expression)->toBe('*/10 * * * *')
        ->and(scheduled('issues:sync osv')->withoutOverlapping)->toBeTrue();
});

it('schedules the digests in Amsterdam time', function () {
    expect(scheduled('digest:send daily')->expression)->toBe('0 8 * * *')
        ->and(scheduled('digest:send daily')->timezone)->toBe('Europe/Amsterdam')
        ->and(scheduled('digest:send weekly')->expression)->toBe('0 8 * * 1')
        ->and(scheduled('sync-runs:prune')->expression)->toBe('0 0 * * *');
});

it('dispatches one job per enabled language and feed', function () {
    Queue::fake();
    $php = Language::factory()->create(['slug' => 'php', 'osv_ecosystems' => ['Packagist'], 'nvd_cpes' => ['cpe:2.3:a:php:php']]);
    Language::factory()->create(['slug' => 'nginx', 'osv_ecosystems' => [], 'nvd_cpes' => ['cpe:2.3:a:f5:nginx', 'cpe:2.3:a:nginx:nginx']]);
    Language::factory()->create(['slug' => 'off', 'enabled' => false, 'osv_ecosystems' => ['PyPI']]);

    $this->artisan('issues:sync osv')->assertSuccessful();
    Queue::assertPushed(SyncFeed::class, 1);
    Queue::assertPushed(SyncFeed::class, fn (SyncFeed $j) => $j->sourceClass === OsvSource::class && $j->languageId === $php->id && $j->feed === 'Packagist');

    $this->artisan('issues:sync nvd')->assertSuccessful();
    Queue::assertPushed(SyncFeed::class, fn (SyncFeed $j) => $j->sourceClass === NvdSource::class && $j->feed === 'cpe:2.3:a:nginx:nginx');
    Queue::assertPushed(SyncFeed::class, 4);
});

it('limits syncing to one language', function () {
    Queue::fake();
    Language::factory()->create(['slug' => 'php', 'osv_ecosystems' => ['Packagist']]);
    Language::factory()->create(['slug' => 'python', 'osv_ecosystems' => ['PyPI']]);

    $this->artisan('issues:sync osv --language=python')->assertSuccessful();

    Queue::assertPushed(SyncFeed::class, fn (SyncFeed $j) => $j->feed === 'PyPI');
    Queue::assertPushed(SyncFeed::class, 1);
});

it('rejects an unknown source', function () {
    $this->artisan('issues:sync carrier-pigeon')->assertFailed();
});

it('prunes sync runs older than 14 days', function () {
    SyncRun::create(['source' => 'osv', 'feed' => 'npm', 'status' => 'ok', 'started_at' => now()->subDays(15)])
        ->forceFill(['created_at' => now()->subDays(15)])->save();
    SyncRun::create(['source' => 'osv', 'feed' => 'npm', 'status' => 'ok', 'started_at' => now()]);

    $this->artisan('sync-runs:prune')->assertSuccessful();

    expect(SyncRun::count())->toBe(1);
});
