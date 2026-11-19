<?php

use App\Enums\Role;
use App\Livewire\Admin\Languages;
use App\Models\Language;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => Role::Superadmin]));
    $this->php = Language::factory()->create(['slug' => 'php', 'name' => 'PHP', 'osv_ecosystems' => ['Packagist'], 'nvd_cpes' => ['cpe:2.3:a:php:php']]);
});

it('lists languages', function () {
    $this->get(route('admin.languages'))->assertOk()->assertSee('PHP')->assertSee('Packagist');
});

it('toggles a language', function () {
    Livewire::test(Languages::class)->call('toggle', $this->php->id);

    expect($this->php->fresh()->enabled)->toBeFalse();
});

it('edits ecosystems and CPEs from comma-separated input', function () {
    Livewire::test(Languages::class)
        ->call('edit', $this->php->id)
        ->assertSet('form.osv_ecosystems', 'Packagist')
        ->set('form.osv_ecosystems', 'Packagist,  PHP-extra ,')
        ->set('form.nvd_cpes', "cpe:2.3:a:php:php, cpe:2.3:a:php:pecl")
        ->call('save')->assertHasNoErrors();

    expect($this->php->fresh()->osv_ecosystems)->toBe(['Packagist', 'PHP-extra'])
        ->and($this->php->fresh()->nvd_cpes)->toBe(['cpe:2.3:a:php:php', 'cpe:2.3:a:php:pecl']);
});

it('adds a language', function () {
    Livewire::test(Languages::class)
        ->call('create')
        ->set('form.slug', 'kotlin')->set('form.name', 'Kotlin')->set('form.kind', 'language')
        ->set('form.osv_ecosystems', 'Maven')->set('form.nvd_cpes', '')
        ->call('save')->assertHasNoErrors();

    expect(Language::where('slug', 'kotlin')->sole()->nvd_cpes)->toBe([]);
});

it('validates CPEs and slugs', function () {
    Livewire::test(Languages::class)->call('create')
        ->set('form.slug', 'php')->set('form.name', 'Dup')->set('form.nvd_cpes', 'not-a-cpe')
        ->call('save')->assertHasErrors(['form.slug', 'form.nvd_cpes']);
});
