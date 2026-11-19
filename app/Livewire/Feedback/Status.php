<?php

namespace App\Livewire\Feedback;

use App\Models\Feedback;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** The private status page for one piece of feedback (link from the confirmation email). */
#[Layout('layouts.app')]
#[Title('Feedback status')]
class Status extends Component
{
    public Feedback $feedback;

    public function render()
    {
        return view('livewire.feedback.status', ['items' => collect([$this->feedback]), 'heading' => 'Your feedback']);
    }
}
