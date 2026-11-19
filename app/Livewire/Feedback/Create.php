<?php

namespace App\Livewire\Feedback;

use App\Enums\FeedbackType;
use App\Enums\Role;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Feedback')]
class Create extends Component
{
    public const PER_HOUR = 5;

    public string $name = '';

    public string $email = '';

    public string $type = 'idea';

    public string $message = '';

    public ?string $sentStatusUrl = null;

    public function mount(): void
    {
        if ($user = auth()->user()) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    public function submit(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email:rfc|max:255',
            'type' => ['required', Rule::enum(FeedbackType::class)],
            'message' => 'required|string|min:5|max:5000',
        ]);

        if (! RateLimiter::attempt('feedback:'.request()->ip(), self::PER_HOUR, fn () => true, 3600)) {
            $this->addError('message', 'You sent a lot of feedback in the last hour. Please try again later.');

            return;
        }

        $feedback = Feedback::query()->create([
            'user_id' => auth()->id(),
            'name' => $this->name,
            'email' => mb_strtolower(trim($this->email)),
            'type' => $this->type,
            'message' => $this->message,
            'token' => Str::random(40),
        ]);

        Notification::route('mail', $feedback->email)->notify(new FeedbackReceived($feedback));
        Notification::send(User::query()->where('role', Role::Superadmin)->get(), new FeedbackReceived($feedback, forAdmin: true));

        $this->sentStatusUrl = $feedback->statusUrl();
        $this->reset('message');
    }

    public function render()
    {
        return view('livewire.feedback.create', ['types' => FeedbackType::cases()]);
    }
}
