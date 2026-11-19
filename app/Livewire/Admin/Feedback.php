<?php

namespace App\Livewire\Admin;

use App\Enums\FeedbackStatus;
use App\Livewire\Concerns\RequiresSuperadmin;
use App\Models\Feedback as FeedbackModel;
use App\Notifications\FeedbackStatusChanged;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Admin → Feedback: set the status and a public note; the submitter is emailed when the status changes. */
#[Layout('layouts.app')]
#[Title('Feedback')]
class Feedback extends Component
{
    use RequiresSuperadmin, WithPagination;

    #[Url(except: 'open')]
    public string $show = 'open';

    public ?int $editingId = null;

    public string $status = 'new';

    public string $note = '';

    public function edit(int $id): void
    {
        $f = FeedbackModel::query()->findOrFail($id);
        $this->editingId = $f->id;
        $this->status = $f->status->value;
        $this->note = (string) $f->public_note;
    }

    public function save(): void
    {
        $this->validate([
            'status' => ['required', Rule::enum(FeedbackStatus::class)],
            'note' => 'nullable|string|max:2000',
        ]);

        $f = FeedbackModel::query()->findOrFail($this->editingId);
        $statusChanged = $f->status->value !== $this->status;
        $f->update(['status' => $this->status, 'public_note' => filled($this->note) ? $this->note : null]);

        if ($statusChanged) {
            Notification::route('mail', $f->email)->notify(new FeedbackStatusChanged($f));
        }
        $this->editingId = null;
        session()->flash('status', $statusChanged ? 'Saved. The submitter was emailed about the new status.' : 'Saved.');
    }

    public function render()
    {
        return view('livewire.admin.feedback', [
            'items' => FeedbackModel::query()
                ->when($this->show === 'open', fn ($q) => $q->whereNotIn('status', [FeedbackStatus::Done, FeedbackStatus::Declined]))
                ->latest()->paginate(25),
            'statuses' => FeedbackStatus::cases(),
        ]);
    }
}
