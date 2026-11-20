<div class="mx-auto max-w-3xl space-y-10 py-8">
    <header>
        <h1 class="feed-head__title">Email settings</h1>
        <p class="mt-2 text-[--muted]">
            Choose the languages and servers you use. New Critical issues are always emailed straight away,
            whatever you pick here, and so are updates to recent Critical issues.
        </p>
    </header>

    <x-status />

    <section aria-labelledby="subs-heading">
        <h2 id="subs-heading" class="text-lg font-semibold">Languages and servers you follow</h2>

        @if ($subscriptions->isEmpty())
            <p class="mt-2 text-sm text-[--muted]">None yet. Add one below.</p>
        @else
            <ul class="feed mt-3">
                @foreach ($subscriptions as $sub)
                    <li wire:key="sub-{{ $sub->id }}" class="flex flex-wrap items-center gap-3 py-3">
                        <span class="min-w-32 font-medium">{{ $sub->language->name }}</span>
                        <label class="sr-only" for="min-{{ $sub->id }}">Minimum severity for {{ $sub->language->name }}</label>
                        <select id="min-{{ $sub->id }}" class="input w-auto py-1"
                                wire:change="updateSubscription({{ $sub->id }}, $event.target.value, '{{ $sub->frequency->value }}')">
                            @foreach ($severities as $s)
                                <option value="{{ $s->value }}" @selected($sub->min_severity === $s)>{{ $s->label() }} and up</option>
                            @endforeach
                        </select>
                        <label class="sr-only" for="freq-{{ $sub->id }}">How often for {{ $sub->language->name }}</label>
                        <select id="freq-{{ $sub->id }}" class="input w-auto py-1"
                                wire:change="updateSubscription({{ $sub->id }}, '{{ $sub->min_severity->value }}', $event.target.value)">
                            @foreach ($frequencies as $f)
                                <option value="{{ $f->value }}" @selected($sub->frequency === $f)>{{ $f->label() }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="unsubscribe({{ $sub->id }})" class="ml-auto text-sm underline">Unsubscribe</button>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($languages->isNotEmpty())
            <form wire:submit="subscribe" class="card mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
                <x-field label="Follow" name="new.language_id">
                    <select id="new.language_id" wire:model="new.language_id" class="input">
                        <option value="">Choose…</option>
                        @foreach ($languages as $language)
                            <option value="{{ $language->id }}">{{ $language->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Severity" name="new.min_severity">
                    <select id="new.min_severity" wire:model="new.min_severity" class="input">
                        @foreach ($severities as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }} and up</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Email me" name="new.frequency">
                    <select id="new.frequency" wire:model="new.frequency" class="input">
                        @foreach ($frequencies as $f)
                            <option value="{{ $f->value }}">{{ $f->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <button type="submit" class="btn-primary">Subscribe</button>
            </form>
        @endif
    </section>

    <section aria-labelledby="hidden-heading">
        <h2 id="hidden-heading" class="text-lg font-semibold">Hidden packages</h2>
        <p class="mt-1 text-sm text-[--muted]">
            Issues of these packages are left out of your lists and emails, unless the issue also affects a package
            you did not hide, or you starred or watched it. Use <code>vendor/*</code> for a whole vendor.
        </p>
        @if ($hidden === [])
            <p class="mt-2 text-sm text-[--muted]">Nothing hidden. Use "Hide" next to a package in the issue list.</p>
        @else
            <ul class="feed mt-3">
                @foreach ($hidden as $pattern)
                    <li wire:key="hidden-{{ $pattern }}" class="flex items-center gap-3 py-2">
                        <code>{{ $pattern }}</code>
                        <button type="button" wire:click="removeHidden(@js($pattern))" class="ml-auto text-sm underline">Show again</button>
                    </li>
                @endforeach
            </ul>
        @endif
        <form wire:submit="addHidden" class="mt-3 flex flex-wrap items-end gap-3">
            <x-field label="Hide a package" name="newHidden" class="min-w-64 flex-1">
                <input id="newHidden" type="text" wire:model="newHidden" class="input" placeholder="silverstripe/framework">
            </x-field>
            <button type="submit" class="btn-secondary">Hide</button>
        </form>
    </section>

    <section aria-labelledby="watch-heading">
        <h2 id="watch-heading" class="text-lg font-semibold">Issues you watch</h2>
        <p class="mt-1 text-sm text-[--muted]">You get an email when one of these changes. Watch an issue from its page.</p>
        @if ($watched->isEmpty())
            <p class="mt-2 text-sm text-[--muted]">You are not watching any issues.</p>
        @else
            <ul class="feed mt-3">
                @foreach ($watched as $issue)
                    <li wire:key="watch-{{ $issue->id }}" class="flex items-center gap-3 py-3">
                        <a href="{{ route('issues.show', $issue->external_id) }}" wire:navigate class="row__link">{{ $issue->external_id }}</a>
                        <span class="truncate">{{ $issue->title }}</span>
                        <button type="button" wire:click="unwatch({{ $issue->id }})" class="ml-auto shrink-0 text-sm underline">Stop watching</button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="name-heading">
        <h2 id="name-heading" class="text-lg font-semibold">Your name</h2>
        <form wire:submit="saveName" class="mt-3 flex max-w-md items-end gap-3">
            <x-field label="Name" name="name" class="flex-1">
                <input id="name" type="text" wire:model="name" class="input" autocomplete="name">
            </x-field>
            <button type="submit" class="btn-secondary">Save name</button>
        </form>
        <p class="mt-2 text-sm text-[--muted]">Signed in as {{ auth()->user()->email }}. <a href="{{ route('feedback.mine') }}" wire:navigate class="underline">See your feedback</a>.</p>
    </section>
</div>
