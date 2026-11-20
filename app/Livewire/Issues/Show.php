<?php

namespace App\Livewire\Issues;

use App\Models\Issue;
use App\Support\Issues\HiddenPackages;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Issue $issue;

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
        $user = auth()->user();
        abort_unless($user, 403);
        if ($user->hidePackage($package)) {
            session()->flash('status', 'Issues of '.HiddenPackages::normalize($package).' are hidden from your lists and emails. Undo in Email settings.');
        }
    }

    public function render()
    {
        return view('livewire.issues.show')->title($this->issue->external_id.' '.$this->issue->title);
    }
}
