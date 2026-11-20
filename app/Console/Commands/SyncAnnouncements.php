<?php

namespace App\Console\Commands;

use App\Announcements\PhpNetFeed;
use Illuminate\Console\Command;

class SyncAnnouncements extends Command
{
    protected $signature = 'announcements:sync';

    protected $description = 'Read the php.net news feed for security announcements and email new ones';

    public function handle(PhpNetFeed $feed): int
    {
        $this->info('Stored '.$feed->sync().' new announcement(s).');

        return self::SUCCESS;
    }
}
