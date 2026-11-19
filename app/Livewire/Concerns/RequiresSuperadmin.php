<?php

namespace App\Livewire\Concerns;

/**
 * Livewire re-runs only its persistent middleware on component updates, so admin components check the
 * role themselves on every request: a superadmin demoted with the page open can no longer act on it.
 */
trait RequiresSuperadmin
{
    public function bootRequiresSuperadmin(): void
    {
        abort_unless(auth()->user()?->fresh()?->isSuperadmin(), 403);
    }
}
