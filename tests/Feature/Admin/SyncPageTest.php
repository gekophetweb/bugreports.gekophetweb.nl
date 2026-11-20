<?php

use App\Enums\Role;
use App\Jobs\SyncFeed;
use App\Livewire\Admin\Sync;
use App\Models\Language;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => Role::Superadmin]));
});

it('lists the latest runs with their errors', function () {
    SyncRun::create(['source' => 'nvd', 'feed' => 'cpe:2.3:a:f5:nginx', 'status' => 'failed', 'error' => 'NVD request failed (HTTP 403).', 'started_at' => now()]);
    SyncRun::create(['source' => 'osv', 'feed' => 'Packagist', 'status' => 'ok', 'created_count' => 4, 'started_at' => now(), 'finished_at' => now()]);

    $this->get(route('admin.sync'))->assertOk()
        ->assertSee('cpe:2.3:a:f5:nginx')->assertSee('NVD request failed (HTTP 403).')
        ->assertSee('Packagist');
});

it('starts a sync on demand', function () {
    Queue::fake();
    Language::factory()->create(['osv_ecosystems' => ['Packagist']]);

    Livewire::test(Sync::class)->call('syncNow', 'osv');

    Queue::assertPushed(SyncFeed::class, 1);
});

it('is forbidden for normal users', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.sync'))->assertForbidden();
});

it('explains how often each source is checked', function () {
    $this->get(route('admin.sync'))->assertOk()
        ->assertSee('Cloudflare Radar Zero Wire')->assertSee('every 30 minutes')->assertSee('no fixed schedule');
});
