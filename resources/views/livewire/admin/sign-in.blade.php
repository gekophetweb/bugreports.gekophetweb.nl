<div class="pb-12">
    <x-admin-nav />
    <h1 class="text-2xl font-semibold">Sign-in</h1>
    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
        Everyone can sign in with a code sent by email. A provider below shows on the sign-in page once it is switched on and has a client id and secret.
    </p>

    <x-status />

    <form wire:submit="save" class="mt-6 max-w-2xl space-y-6">
        @foreach ($labels as $slug => $label)
            <fieldset class="card space-y-4">
                <legend class="sr-only">{{ $label }}</legend>
                <label class="flex items-center gap-2 font-medium">
                    <input type="checkbox" wire:model="providers.{{ $slug }}.enabled" class="size-4">
                    Sign in with {{ $label }}
                </label>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Authorised redirect URI: <code class="select-all break-all">{{ route('sso.callback', $slug) }}</code>
                </p>
                <x-field label="Client id" name="providers.{{ $slug }}.client_id">
                    <input id="providers.{{ $slug }}.client_id" type="text" wire:model="providers.{{ $slug }}.client_id" class="input" autocomplete="off">
                </x-field>
                <x-field label="Client secret" name="providers.{{ $slug }}.client_secret"
                         :hint="$providers[$slug]['has_secret'] ? 'A secret is stored. Leave blank to keep it.' : null">
                    <input id="providers.{{ $slug }}.client_secret" type="password" wire:model="providers.{{ $slug }}.client_secret" class="input" autocomplete="new-password">
                </x-field>
            </fieldset>
        @endforeach
        <button type="submit" class="btn-primary">Save</button>
    </form>
</div>
