<?php

use App\Enums\Role;
use App\Livewire\Admin\Users;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Superadmin]);
    $this->actingAs($this->admin);
});

it('lists users', function () {
    $user = User::factory()->create(['email' => 'someone@example.test']);

    $this->get(route('admin.users'))->assertOk()->assertSee('someone@example.test');
});

it('promotes and demotes users', function () {
    $user = User::factory()->create();

    Livewire::test(Users::class)->call('setRole', $user->id, 'superadmin');
    expect($user->fresh()->role)->toBe(Role::Superadmin);

    Livewire::test(Users::class)->call('setRole', $user->id, 'user');
    expect($user->fresh()->role)->toBe(Role::User);
});

it('cannot demote the last superadmin', function () {
    Livewire::test(Users::class)->call('setRole', $this->admin->id, 'user')->assertHasErrors('role');

    expect($this->admin->fresh()->role)->toBe(Role::Superadmin);
});

it('rejects an unknown role', function () {
    $user = User::factory()->create();

    Livewire::test(Users::class)->call('setRole', $user->id, 'god')->assertHasErrors('role');
});
