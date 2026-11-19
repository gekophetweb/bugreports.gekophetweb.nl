<div class="pb-12">
    <x-admin-nav />
    <h1 class="text-2xl font-semibold">Outgoing mail</h1>
    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Used for sign-in codes, subscriptions, alerts and feedback updates.</p>

    <x-status />

    <form wire:submit="save" class="card mt-6 max-w-2xl space-y-4">
        <x-field label="Send mail with" name="mailer">
            <select id="mailer" wire:model.live="mailer" class="input">
                @foreach ($mailers as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-field>

        @if ($mailer === 'smtp')
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="Host" name="host" class="sm:col-span-2">
                    <input id="host" type="text" wire:model="host" class="input" placeholder="smtp.example.com">
                </x-field>
                <x-field label="Port" name="port">
                    <input id="port" type="number" wire:model="port" class="input">
                </x-field>
            </div>
            <x-field label="Encryption" name="scheme" hint="Port 587 usually uses STARTTLS (smtp), port 465 uses TLS (smtps).">
                <select id="scheme" wire:model="scheme" class="input">
                    <option value="">Automatic</option>
                    <option value="smtp">STARTTLS (smtp)</option>
                    <option value="smtps">TLS (smtps)</option>
                </select>
            </x-field>
            <x-field label="Username" name="username">
                <input id="username" type="text" wire:model="username" class="input" autocomplete="off">
            </x-field>
            <x-field label="Password" name="password" :hint="($stored['password'] ?? false) ? 'A password is stored. Leave blank to keep it.' : null">
                <input id="password" type="password" wire:model="password" class="input" autocomplete="new-password">
            </x-field>
        @elseif ($mailer === 'resend')
            <x-field label="Resend API key" name="resend_key" :hint="($stored['resend_key'] ?? false) ? 'A key is stored. Leave blank to keep it.' : 'Starts with re_.'">
                <input id="resend_key" type="password" wire:model="resend_key" class="input" autocomplete="off">
            </x-field>
        @elseif ($mailer === 'mailgun')
            <x-field label="Mailgun domain" name="mailgun_domain">
                <input id="mailgun_domain" type="text" wire:model="mailgun_domain" class="input" placeholder="mg.example.com">
            </x-field>
            <x-field label="Mailgun API key" name="mailgun_secret" :hint="($stored['mailgun_secret'] ?? false) ? 'A key is stored. Leave blank to keep it.' : null">
                <input id="mailgun_secret" type="password" wire:model="mailgun_secret" class="input" autocomplete="off">
            </x-field>
            <x-field label="Region" name="mailgun_endpoint">
                <select id="mailgun_endpoint" wire:model="mailgun_endpoint" class="input">
                    <option value="api.mailgun.net">US (api.mailgun.net)</option>
                    <option value="api.eu.mailgun.net">EU (api.eu.mailgun.net)</option>
                </select>
            </x-field>
        @elseif ($mailer === 'postmark')
            <x-field label="Postmark server token" name="postmark_token" :hint="($stored['postmark_token'] ?? false) ? 'A token is stored. Leave blank to keep it.' : null">
                <input id="postmark_token" type="password" wire:model="postmark_token" class="input" autocomplete="off">
            </x-field>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="From address" name="from_address">
                <input id="from_address" type="email" wire:model="from_address" class="input">
            </x-field>
            <x-field label="From name" name="from_name">
                <input id="from_name" type="text" wire:model="from_name" class="input">
            </x-field>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">Save</button>
            <button type="button" wire:click="sendTest" class="btn-secondary">Send test email to me</button>
        </div>
        @error('test')
            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </form>
</div>
