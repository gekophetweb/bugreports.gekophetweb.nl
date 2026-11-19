<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresSuperadmin;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Sources\SyncDispatcher;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Admin → Sync: recent sync runs, their errors, the cursors, and a "sync now" button per source. */
#[Layout('layouts.app')]
#[Title('Sync')]
class Sync extends Component
{
    use RequiresSuperadmin, WithPagination;

    public string $status = '';

    public function syncNow(string $source): void
    {
        abort_unless(array_key_exists($source, SyncDispatcher::SOURCES), 404);
        $count = app(SyncDispatcher::class)->dispatch($source);
        session()->flash('status', "Queued $count ".strtoupper($source).' feed(s). Refresh in a minute to see the results.');
    }

    public function render()
    {
        return view('livewire.admin.sync', [
            'runs' => SyncRun::query()->with('language')
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->latest('id')->paginate(50),
            'cursors' => SyncCursor::query()->orderBy('source')->orderBy('feed')->get(),
            'sources' => array_keys(SyncDispatcher::SOURCES),
        ]);
    }
}
