<?php

use App\Enums\Role;
use App\Jobs\SyncFeed;
use App\Livewire\Admin\SetupGuide;
use App\Models\Language;
use App\Models\User;
use App\Sources\Nvd\NvdSource;
use App\Support\Nvd\NvdApiKey;
use App\Support\Settings;
use App\Support\Sleeper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => Role::Superadmin]));
});

it('explains how to get the Google, GitHub and NVD keys with this site\'s URLs', function () {
    $this->get(route('admin.setup'))->assertOk()
        ->assertSee('Google sign-in')->assertSee('console.cloud.google.com', false)
        ->assertSee(route('sso.callback', 'google'))
        ->assertSee('GitHub sign-in')->assertSee('github.com/settings/developers', false)
        ->assertSee(route('sso.callback', 'github'))
        ->assertSee('NVD API key')->assertSee('nvd.nist.gov/developers/request-an-api-key', false);
});

it('shows what still needs to be done', function () {
    $page = Livewire::test(SetupGuide::class);
    $page->assertViewHas('checks', fn ($c) => $c['mail'] === false && $c['google'] === false && $c['nvd'] === false);

    Settings::set('mail.mailer', 'smtp');
    Settings::set('sso.google.client_id', 'id');
    Settings::set('sso.google.client_secret', 'secret', secret: true);
    Settings::set('nvd.api_key', 'k', secret: true);

    Livewire::test(SetupGuide::class)->assertViewHas('checks', fn ($c) => $c['mail'] && $c['google'] && $c['nvd']);
});

it('does not count the log mailer as set up', function () {
    Settings::set('mail.mailer', 'log');

    Livewire::test(SetupGuide::class)->assertViewHas('checks', fn ($c) => $c['mail'] === false);
});

it('stores the NVD key encrypted and never shows it again', function () {
    Livewire::test(SetupGuide::class)->set('nvdKey', ' abc-123 ')->call('saveNvdKey')->assertHasNoErrors()->assertSet('nvdKey', '');

    expect(NvdApiKey::get())->toBe('abc-123')
        ->and(DB::table('settings')->where('key', 'nvd.api_key')->value('value'))->not->toContain('abc-123');
    $this->get(route('admin.setup'))->assertDontSee('abc-123');
});

it('keeps the stored key when saved blank, and removes it on request', function () {
    Settings::set('nvd.api_key', 'abc', secret: true);

    Livewire::test(SetupGuide::class)->set('nvdKey', '')->call('saveNvdKey');
    expect(NvdApiKey::get())->toBe('abc');

    Livewire::test(SetupGuide::class)->call('removeNvdKey');
    expect(NvdApiKey::get())->toBeNull();
});

it('prefers the key from the website over the one in .env', function () {
    config(['issues.nvd_api_key' => 'from-env']);
    expect(NvdApiKey::get())->toBe('from-env')->and(NvdApiKey::source())->toBe('env');

    Settings::set('nvd.api_key', 'from-site', secret: true);
    expect(NvdApiKey::get())->toBe('from-site')->and(NvdApiKey::source())->toBe('site');
});

it('uses the stored key when syncing NVD', function () {
    Settings::set('nvd.api_key', 'site-key', secret: true);
    app()->instance(Sleeper::class, new class extends Sleeper
    {
        public function sleep(float $seconds): void {}
    });
    Http::fake(fn () => Http::response(['resultsPerPage' => 2000, 'startIndex' => 0, 'totalResults' => 0, 'vulnerabilities' => []]));
    $nginx = Language::factory()->create(['nvd_cpes' => ['cpe:2.3:a:f5:nginx']]);

    SyncFeed::dispatchSync(NvdSource::class, $nginx->id, 'cpe:2.3:a:f5:nginx');

    Http::assertSent(fn (Request $r) => $r->hasHeader('apiKey', 'site-key'));
});

it('tests the NVD key against the API', function () {
    Settings::set('nvd.api_key', 'good', secret: true);
    Http::fake(['services.nvd.nist.gov/*' => Http::response(['totalResults' => 1, 'vulnerabilities' => []])]);

    Livewire::test(SetupGuide::class)->call('testNvdKey')->assertHasNoErrors()->assertSet('nvdTest', 'ok');
    Http::assertSent(fn (Request $r) => $r->hasHeader('apiKey', 'good'));
});

it('reports a rejected NVD key', function () {
    Settings::set('nvd.api_key', 'bad', secret: true);
    Http::fake(['services.nvd.nist.gov/*' => Http::response('', 404)]);

    Livewire::test(SetupGuide::class)->call('testNvdKey')->assertHasErrors('nvdKey');
});

it('asks for a key before testing', function () {
    Livewire::test(SetupGuide::class)->call('testNvdKey')->assertHasErrors('nvdKey');
});

it('is for superadmins only', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.setup'))->assertForbidden();
});
