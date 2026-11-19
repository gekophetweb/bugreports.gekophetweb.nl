<?php

namespace App\Livewire\Issues;

use App\Livewire\Concerns\TogglesIssueFlags;
use App\Models\Issue;
use App\Models\IssueWatch;
use Livewire\Attributes\Locked;
use Livewire\Component;

class WatchToggle extends Component
{
    use TogglesIssueFlags;

    #[Locked]
    public Issue $issue;

    public bool $watching = false;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
        $this->watching = (bool) auth()->user()?->isWatching($issue);
    }

    public function toggle(): void
    {
        $state = $this->flip(IssueWatch::class, $this->issue, route('issues.show', $this->issue));
        if ($state !== null) {
            $this->watching = $state;
        }
    }

    public function render()
    {
        return <<<'BLADE'
            <button type="button" wire:click="toggle" class="btn-secondary" aria-pressed="{{ $watching ? 'true' : 'false' }}"
                    title="Get an email when this issue changes">
                {{ $watching ? 'Watching: email me updates' : 'Watch for updates' }}
            </button>
            BLADE;
    }
}
