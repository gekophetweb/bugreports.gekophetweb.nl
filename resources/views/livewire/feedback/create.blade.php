<div class="mx-auto max-w-xl py-8">
    <h1 class="feed-head__title">Send feedback</h1>
    <p class="mt-2 text-(--muted)">Found a problem, missing a language, or have an idea? You get a link to follow what happens with it.</p>

    @if ($sentStatusUrl)
        <div role="status" class="card mt-6">
            <p class="font-medium">Thanks, we received your feedback.</p>
            <p class="mt-1 text-sm text-(--muted)">We also emailed you this link. We email you again when the status changes.</p>
            <a href="{{ $sentStatusUrl }}" class="mt-3 inline-block text-(--signal) underline">Follow its status</a>
        </div>
    @endif

    <form wire:submit="submit" class="mt-6 space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Name" name="name">
                <input id="name" type="text" wire:model="name" class="input" autocomplete="name" required>
            </x-field>
            <x-field label="Email" name="email">
                <input id="email" type="email" wire:model="email" class="input" autocomplete="email" required>
            </x-field>
        </div>
        <fieldset>
            <legend class="mb-1 text-sm font-medium">What is it about?</legend>
            <div class="flex flex-wrap gap-4 text-sm">
                @foreach ($types as $t)
                    <label class="inline-flex items-center gap-2"><input type="radio" wire:model="type" value="{{ $t->value }}"> {{ $t->label() }}</label>
                @endforeach
            </div>
            @error('type')<p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </fieldset>
        <x-field label="Message" name="message">
            <textarea id="message" wire:model="message" rows="6" class="input" required></textarea>
        </x-field>
        <button type="submit" class="btn-primary">Send feedback</button>
    </form>
</div>
