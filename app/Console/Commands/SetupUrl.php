<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupUrl extends Command
{
    protected $signature = 'setup:url';

    protected $description = 'Print the one-time link that creates the superadmin (only while there is none)';

    public function handle(): int
    {
        if (User::superadminExists()) {
            return self::SUCCESS;
        }

        $token = Settings::get('setup.token');
        if (blank($token)) {
            $token = Str::random(40);
            Settings::set('setup.token', $token, secret: true);
        }

        $this->line('');
        $this->line('  No superadmin yet. Open this link to create one (works once):');
        $this->line('  '.route('setup', ['token' => $token]));
        $this->line('');

        return self::SUCCESS;
    }
}
