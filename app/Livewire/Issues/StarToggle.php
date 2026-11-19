<?php

namespace App\Livewire\Issues;

use App\Livewire\Concerns\TogglesIssueFlags;
use App\Models\Issue;
use App\Models\IssueStar;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StarToggle extends Component
{
    use TogglesIssueFlags;

    #[Locked]
    public Issue $issue;

    public bool $starred = false;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
        $this->starred = (bool) auth()->user()?->hasStarred($issue);
    }

    public function toggle(): void
    {
        $state = $this->flip(IssueStar::class, $this->issue, route('issues.show', $this->issue));
        if ($state !== null) {
            $this->starred = $state;
        }
    }

    public function render()
    {
        return <<<'BLADE'
            <button type="button" wire:click="toggle" class="btn-secondary" aria-pressed="{{ $starred ? 'true' : 'false' }}"
                    title="Starred issues are shown first in your lists">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" class="mr-1.5"
                     fill="{{ $starred ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2"><path d="M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9z"/></svg>
                {{ $starred ? 'Starred' : 'Star' }}
            </button>
            BLADE;
    }
}
