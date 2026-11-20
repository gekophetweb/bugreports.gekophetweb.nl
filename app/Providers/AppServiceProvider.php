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

        // Packages a visitor hid or uses in this browser move into their account when they sign in.
        Event::listen(Login::class, function (Login $event) {
            if (! $event->user instanceof User) {
                return;
            }
            foreach ([HiddenPackages::COOKIE => 'hidden_packages', HiddenPackages::USED_COOKIE => 'used_packages'] as $cookie => $column) {
                $fromCookie = HiddenPackages::fromCookie($cookie);
                if ($fromCookie !== []) {
                    $event->user->update([$column => HiddenPackages::clean([...($event->user->{$column} ?? []), ...$fromCookie])]);
                    HiddenPackages::forgetCookie($cookie);
                }
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
