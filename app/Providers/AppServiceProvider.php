<?php

namespace App\Providers;

use App\Support\Mail\MailConfigurator;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->applyRuntimeSettings();

        // Queue workers boot once; pick up settings changed in the website before each job.
        Event::listen(JobProcessing::class, fn () => $this->applyRuntimeSettings());
    }

    private function applyRuntimeSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (Throwable) {
            return; // no database yet (e.g. during image build)
        }

        app(MailConfigurator::class)->apply();
    }
}
