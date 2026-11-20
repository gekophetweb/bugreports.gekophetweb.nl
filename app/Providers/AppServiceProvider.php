<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Issues\HiddenPackages;
use App\Support\Mail\MailConfigurator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
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
        // Stored times are UTC; ->inDisplayTz() converts for display (Europe/Amsterdam by default).
        foreach ([Carbon::class, CarbonImmutable::class] as $class) {
            $class::macro('inDisplayTz', fn () => $this->copy()->setTimezone(config('app.display_timezone')));
        }

        $this->applyRuntimeSettings();

        // Packages a visitor hid in this browser move into their account when they sign in.
        Event::listen(Login::class, function (Login $event) {
            $fromCookie = HiddenPackages::fromCookie();
            if ($fromCookie !== [] && $event->user instanceof User) {
                $event->user->update(['hidden_packages' => HiddenPackages::clean([...$event->user->hiddenPackages(), ...$fromCookie])]);
                HiddenPackages::forgetCookie();
            }
        });

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
