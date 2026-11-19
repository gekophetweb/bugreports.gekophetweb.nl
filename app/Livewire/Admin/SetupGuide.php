<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresSuperadmin;
use App\Support\Auth\SsoProviders;
use App\Support\Nvd\NvdApiKey;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Admin → Setup: what is configured yet, and step-by-step guides for getting the Google and GitHub
 * OAuth credentials and an NVD API key. The NVD key can be saved here (encrypted).
 */
#[Layout('layouts.app')]
#[Title('Setup')]
class SetupGuide extends Component
{
    use RequiresSuperadmin;

    public string $nvdKey = '';

    /** null, or 'ok' after a successful test */
    public ?string $nvdTest = null;

    public function saveNvdKey(): void
    {
        $this->nvdKey = trim($this->nvdKey); // pasted keys often carry spaces or a newline
        $this->validate(['nvdKey' => 'nullable|string|max:200|regex:/^[A-Za-z0-9-]*$/'], [
            'nvdKey.regex' => 'An NVD API key only has letters, digits and dashes. Copy it from the NVD email or page again.',
        ]);

        if (trim($this->nvdKey) !== '') {
            Settings::set('nvd.api_key', trim($this->nvdKey), secret: true);
            session()->flash('status', 'NVD API key saved. NVD is now queried about ten times faster.');
        }
        $this->nvdKey = '';
        $this->nvdTest = null;
    }

    public function removeNvdKey(): void
    {
        Settings::set('nvd.api_key', null);
        $this->nvdTest = null;
        session()->flash('status', 'NVD API key removed.');
    }

    public function testNvdKey(): void
    {
        $this->nvdTest = null;
        $key = NvdApiKey::get();
        if (! $key) {
            $this->addError('nvdKey', 'Save an API key first, then test it.');

            return;
        }

        try {
            $response = Http::timeout(20)->acceptJson()->withHeaders(['apiKey' => $key])
                ->get('https://services.nvd.nist.gov/rest/json/cves/2.0', ['resultsPerPage' => 1]);
        } catch (Throwable $e) {
            $this->addError('nvdKey', 'NVD could not be reached: '.$e->getMessage());

            return;
        }

        if ($response->successful()) {
            $this->nvdTest = 'ok';

            return;
        }

        $this->addError('nvdKey', match ($response->status()) {
            403, 404 => 'NVD rejected this key (HTTP '.$response->status().'). Check that you activated it from the email, and that it was copied completely.',
            429 => 'NVD is rate limiting right now (HTTP 429). Try again in a minute.',
            default => 'NVD answered with HTTP '.$response->status().'. Try again later.',
        });
    }

    public function render()
    {
        $mailer = Settings::get('mail.mailer');
        $enabled = SsoProviders::enabled();

        return view('livewire.admin.setup-guide', [
            'checks' => [
                'mail' => filled($mailer) && $mailer !== 'log',
                'google' => array_key_exists('google', $enabled),
                'github' => array_key_exists('github', $enabled),
                'nvd' => NvdApiKey::get() !== null,
                'https' => str_starts_with((string) config('app.url'), 'https://'),
            ],
            'nvdSource' => NvdApiKey::source(),
            'appUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }
}
