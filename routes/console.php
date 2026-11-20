<?php

use App\Models\SyncRun;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('sync-runs:prune {--days=14}', function () {
    $deleted = SyncRun::query()->where('created_at', '<', now()->subDays((int) $this->option('days')))->delete();
    $this->info("Deleted $deleted sync run(s).");
})->purpose('Delete sync run logs older than 14 days');

$displayTz = config('app.display_timezone');

Schedule::command('issues:sync osv')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('issues:sync nvd')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('announcements:sync')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('issues:sync ghsa')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('issues:sync zerowire')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('digest:send daily')->dailyAt('08:00')->timezone($displayTz)->withoutOverlapping()->onOneServer();
Schedule::command('digest:send weekly')->weeklyOn(1, '08:00')->timezone($displayTz)->withoutOverlapping()->onOneServer();
Schedule::command('sync-runs:prune')->daily()->withoutOverlapping()->onOneServer();
