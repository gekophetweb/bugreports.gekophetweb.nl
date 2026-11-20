<?php

namespace App\Livewire\Issues;

use App\Models\Issue;
use App\Support\Issues\HiddenPackages;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Issue $issue;

    /** @var list<string>|null a visitor's hidden packages after a change on this page (null: read the cookie) */
    #[Locked]
    public ?array $visitorHidden = null;

    /**
     * Looked up by its id, or by an older id it now lists as an alias (a CVE first imported from NVD and
     * later taken over by its advisory), which redirects so links in sent emails keep working.
     */
    public function mount(string $id)
    {
        $issue = Issue::query()->where('external_id', $id)->first();
        if (! $issue) {
            $current = Issue::query()->withAlias($id)->orderBy('id')->first();
            abort_unless($current, 404);

            return $this->redirectRoute('issues.show', $current);
        }

        $this->issue = $issue->load('languages:id,slug,name');
    }

    /** Hides issues of this package for the signed-in user, on the site and in emails. */
    public function hidePackage(string $package): void
    {
        $package = HiddenPackages::normalize($package);
        if (! HiddenPackages::isValid($package)) {
            return;
        }

        if ($user = auth()->user()) {
            $user->hidePackage($package);
            session()->flash('status', "Issues of $package are hidden from your lists and emails. Undo in Email settings.");

            return;
        }

        $this->visitorHidden = HiddenPackages::clean([...HiddenPackages::fromCookie(), ...$this->visitorHidden ?? [], $package]);
        HiddenPackages::queueCookie($this->visitorHidden);
        session()->flash('status', "Issues of $package are hidden in this browser. Show them again from the issue list.");
    }

    /** @return list<string> */
    public function hiddenPackages(): array
    {
        return auth()->user()?->hiddenPackages() ?? $this->visitorHidden ?? HiddenPackages::fromCookie();
    }

    public function render()
    {
        return view('livewire.issues.show')->title($this->issue->external_id.' '.$this->issue->title);
    }
}
