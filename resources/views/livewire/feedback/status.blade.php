<div class="mx-auto max-w-2xl py-8">
    <h1 class="feed-head__title">{{ $heading }}</h1>

    @forelse ($items as $f)
        <article wire:key="fb-{{ $f->id }}" class="card mt-6">
            <div class="flex flex-wrap items-center gap-3">
                <span class="status-pill status-pill--{{ $f->status->value }}">{{ $f->status->label() }}</span>
                <span class="text-sm text-[--muted]">{{ $f->type->label() }}, sent {{ $f->created_at->inDisplayTz()->format('j M Y') }}</span>
            </div>
            <p class="mt-3 whitespace-pre-line">{{ $f->message }}</p>
            @if ($f->public_note)
                <div class="mt-4 border-l-4 border-[--signal] pl-3 text-sm">
                    <p class="font-semibold">Reply</p>
                    <p class="mt-1 whitespace-pre-line">{{ $f->public_note }}</p>
                </div>
            @endif
        </article>
    @empty
        <div class="empty">
            <p class="font-medium">You have not sent any feedback yet.</p>
            <a href="{{ route('feedback.create') }}" wire:navigate class="btn-primary mt-4">Send feedback</a>
        </div>
    @endforelse
</div>
