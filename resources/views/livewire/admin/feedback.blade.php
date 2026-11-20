<div class="pb-12">
    <x-admin-nav />
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">Feedback</h1>
        <label class="ml-auto flex items-center gap-2 text-sm">Show
            <select wire:model.live="show" class="input w-auto py-1">
                <option value="open">Open</option>
                <option value="all">All</option>
            </select>
        </label>
    </div>

    <div class="mt-4"><x-status /></div>

    <div class="mt-4 space-y-4">
        @forelse ($items as $f)
            <article wire:key="afb-{{ $f->id }}" class="card">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="status-pill status-pill--{{ $f->status->value }}">{{ $f->status->label() }}</span>
                    <span class="text-sm">{{ $f->name }} &lt;{{ $f->email }}&gt;</span>
                    <span class="text-sm text-(--muted)">{{ $f->type->label() }}, {{ $f->created_at->inDisplayTz()->format('j M Y H:i') }}</span>
                    @if ($editingId !== $f->id)
                        <button type="button" wire:click="edit({{ $f->id }})" class="ml-auto text-sm underline">Change status</button>
                    @endif
                </div>
                <p class="mt-3 whitespace-pre-line">{{ $f->message }}</p>
                @if ($f->public_note && $editingId !== $f->id)
                    <p class="mt-3 text-sm"><span class="font-semibold">Reply:</span> {{ $f->public_note }}</p>
                @endif

                @if ($editingId === $f->id)
                    <form wire:submit="save" class="mt-4 space-y-3 border-t border-(--line) pt-4">
                        <x-field label="Status" name="status">
                            <select id="status" wire:model="status" class="input w-auto">
                                @foreach ($statuses as $s)
                                    <option value="{{ $s->value }}">{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field label="Reply shown to the submitter" name="note">
                            <textarea id="note" wire:model="note" rows="3" class="input"></textarea>
                        </x-field>
                        <div class="flex gap-3">
                            <button type="submit" class="btn-primary">Save</button>
                            <button type="button" wire:click="$set('editingId', null)" class="btn-secondary">Cancel</button>
                        </div>
                    </form>
                @endif
            </article>
        @empty
            <div class="empty"><p>No open feedback.</p></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $items->links() }}</div>
</div>
