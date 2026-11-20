<?php

namespace App\Console\Commands;

use App\Sources\SyncDispatcher;
use Illuminate\Console\Command;

class SyncSource extends Command
{
    protected $signature = 'issues:sync {source : osv, nvd or ghsa} {--language= : only this language slug}';

    protected $description = 'Queue a sync of every enabled language feed for one source';

    public function handle(SyncDispatcher $dispatcher): int
    {
        $source = (string) $this->argument('source');
        if (! array_key_exists($source, SyncDispatcher::SOURCES)) {
            $this->error('Source must be one of: '.implode(', ', array_keys(SyncDispatcher::SOURCES)).'.');

            return self::FAILURE;
        }

        $count = $dispatcher->dispatch($source, $this->option('language') ?: null);
        $this->info("Queued $count $source feed(s).");

        return self::SUCCESS;
    }
}
