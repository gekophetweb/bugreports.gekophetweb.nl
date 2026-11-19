<?php

namespace App\Livewire\Admin;

use App\Support\Auth\SsoProviders;
use App\Support\Settings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Admin → Sign-in: SSO providers (Google, GitHub). Client secrets are never shown again. */
#[Layout('layouts.app')]
#[Title('Sign-in settings')]
class SignIn extends Component
{
    /** @var array<string, array{enabled: bool, client_id: string, client_secret: string, has_secret: bool}> */
    public array $providers = [];

    public function mount(): void
    {
        foreach (array_keys(SsoProviders::PROVIDERS) as $slug) {
            $this->providers[$slug] = [
                'enabled' => SsoProviders::isEnabled($slug),
                'client_id' => (string) Settings::get("sso.$slug.client_id", ''),
                'client_secret' => '',
                'has_secret' => filled(Settings::get("sso.$slug.client_secret")),
            ];
        }
    }

    protected function rules(): array
    {
        $rules = [];
        foreach (array_keys(SsoProviders::PROVIDERS) as $slug) {
            $rules["providers.$slug.enabled"] = 'boolean';
            $rules["providers.$slug.client_id"] = 'nullable|string|max:255';
            $rules["providers.$slug.client_secret"] = 'nullable|string|max:1000';
        }

        return $rules;
    }

    public function save(): void
    {
        $this->validate();

        foreach (array_keys(SsoProviders::PROVIDERS) as $slug) {
            $p = $this->providers[$slug];
            Settings::set("sso.$slug.enabled", $p['enabled'] ? '1' : '0');
            Settings::set("sso.$slug.client_id", filled($p['client_id']) ? trim($p['client_id']) : null);
            if (filled($p['client_secret'])) {
                Settings::set("sso.$slug.client_secret", trim($p['client_secret']), secret: true);
                $this->providers[$slug]['has_secret'] = true;
            }
            $this->providers[$slug]['client_secret'] = '';
        }

        session()->flash('status', 'Sign-in settings saved.');
    }

    public function render()
    {
        return view('livewire.admin.sign-in', ['labels' => array_map(fn ($p) => $p['label'], SsoProviders::PROVIDERS)]);
    }
}
