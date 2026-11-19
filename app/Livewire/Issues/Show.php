<?php

namespace App\Livewire\Issues;

use App\Models\Issue;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Issue $issue;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue->load('languages:id,slug,name');
    }

    public function render()
    {
        return view('livewire.issues.show')->title($this->issue->external_id.' '.$this->issue->title);
    }
}
