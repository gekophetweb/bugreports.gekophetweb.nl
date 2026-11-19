<?php

namespace App\Livewire\Feedback;

use App\Models\Feedback;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My feedback')]
class Mine extends Component
{
    public function render()
    {
        $user = auth()->user();

        return view('livewire.feedback.status', [
            'items' => Feedback::query()->where('user_id', $user->id)->orWhere('email', $user->email)->latest()->get(),
            'heading' => 'My feedback',
        ]);
    }
}
