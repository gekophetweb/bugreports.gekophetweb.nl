<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Admin → Users: promote users to superadmin and back. The last superadmin cannot be demoted. */
#[Layout('layouts.app')]
#[Title('Users')]
class Users extends Component
{
    use WithPagination;

    public string $search = '';

    public function setRole(int $userId, string $role): void
    {
        $role = Role::tryFrom($role);
        if (! $role) {
            $this->addError('role', 'Unknown role.');

            return;
        }

        DB::transaction(function () use ($userId, $role) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $superadmins = User::query()->where('role', Role::Superadmin)->lockForUpdate()->pluck('id')->count();

            if ($user->isSuperadmin() && $role !== Role::Superadmin && $superadmins <= 1) {
                $this->addError('role', 'There must always be at least one superadmin.');

                return;
            }

            $user->update(['role' => $role]);
        });
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('email', 'ilike', '%'.$this->search.'%')->orWhere('name', 'ilike', '%'.$this->search.'%')))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.admin.users', ['users' => $users, 'roles' => Role::cases()]);
    }
}
