<div class="mx-auto max-w-md py-12">
    <h1 class="text-2xl font-semibold">Create the superadmin</h1>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        This account manages the site: mail, sign-in, languages and users. After this, you sign in with an emailed code or SSO.
    </p>

    <form wire:submit="save" class="mt-6 space-y-4">
        <x-field label="Name" name="name">
            <input id="name" type="text" wire:model="name" autocomplete="name" class="input" required>
        </x-field>
        <x-field label="Email" name="email">
            <input id="email" type="email" wire:model="email" autocomplete="email" class="input" required>
        </x-field>
        <button type="submit" class="btn-primary w-full">Create superadmin</button>
    </form>
</div>
