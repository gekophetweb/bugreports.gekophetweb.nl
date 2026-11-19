<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * First run: the link printed by `php artisan setup:url` creates the superadmin.
 * Gone for good once a superadmin exists.
 */
#[Layout('layouts.app')]
#[Title('Set up')]
class Setup extends Component
{
    #[Locked]
    public string $token = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email:rfc|max:255')]
    public string $email = '';

    public function mount(string $token): void
    {
        abort_unless(self::tokenIsValid($token), 404);
        $this->token = $token;
    }

    public function save()
    {
        $this->validate();

        $user = DB::transaction(function () {
            // Serialises concurrent attempts: the second waits on this row lock, then finds the token gone.
            Setting::query()->whereKey('setup.token')->lockForUpdate()->first();
            Settings::flush();
            abort_unless(self::tokenIsValid($this->token), 404);

            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'role' => Role::Superadmin,
                'email_verified_at' => now(),
            ]);
            Settings::set('setup.token', null);

            return $user;
        });

        Auth::login($user, remember: true);
        session()->regenerate();

        return $this->redirectRoute('admin.setup');
    }

    private static function tokenIsValid(string $token): bool
    {
        $stored = Settings::get('setup.token');

        return ! User::superadminExists() && filled($stored) && hash_equals($stored, $token);
    }

    public function render()
    {
        return view('livewire.setup');
    }
}
