<div class="pb-12">
    <x-admin-nav />
    <h1 class="feed-head__title">Setup</h1>
    <p class="mt-2 max-w-2xl text-(--muted)">
        What this site still needs, and how to get the keys for it. You paste the keys on the Mail and Sign-in pages,
        or for NVD right here. Every key is stored encrypted and is never shown again.
    </p>

    <div class="mt-4"><x-status /></div>

    <section class="card mt-6 max-w-3xl" aria-labelledby="checklist">
        <h2 id="checklist" class="text-lg font-semibold">Checklist</h2>
        <ul class="mt-2 divide-y divide-(--line)">
            <x-setup-check :done="$checks['https']">
                The site runs on HTTPS
                <x-slot:hint>APP_URL in .env is {{ $appUrl }}. Sign-in providers only accept the exact address, so set it to the public HTTPS address before creating the keys below.</x-slot:hint>
            </x-setup-check>
            <x-setup-check :done="$checks['mail']" :href="route('admin.mail')">
                Outgoing mail
                <x-slot:hint>Needed for sign-in codes and all alerts. Until then, emails only go to the log.</x-slot:hint>
            </x-setup-check>
            <x-setup-check :done="$checks['google']" :href="route('admin.sign-in')">
                Google sign-in
            </x-setup-check>
            <x-setup-check :done="$checks['github']" :href="route('admin.sign-in')" optional>
                GitHub sign-in
            </x-setup-check>
            <x-setup-check :done="$checks['nvd']" href="#nvd" optional>
                NVD API key
                <x-slot:hint>Without one, NVD allows 5 requests per 30 seconds; with one, 50.</x-slot:hint>
            </x-setup-check>
        </ul>
    </section>

    <div class="mt-10 grid max-w-3xl gap-10">

        <section id="google" aria-labelledby="google-h">
            <h2 id="google-h" class="text-xl font-semibold">Google sign-in</h2>
            <p class="mt-1 text-sm text-(--muted)">Takes about 5 minutes. You need a Google account; a Google Cloud project is free.</p>
            <ol class="guide">
                <li>Open the <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener noreferrer">Google Cloud console</a> and create a project, for example <em>bugreports</em>. Make sure it is selected in the top bar.</li>
                <li>Go to <a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener noreferrer">Google Auth Platform</a> and click <strong>Get started</strong>. Enter the app name and your support email, choose <strong>External</strong> as audience, add your contact email and finish.</li>
                <li>Under <strong>Audience</strong>, click <strong>Publish app</strong>, so anyone with a Google account can sign in, not only test users. The site only asks for name and email, so Google does not need to review it.</li>
                <li>Under <strong>Clients</strong>, click <strong>Create client</strong> and choose <strong>Web application</strong>.</li>
                <li>
                    Under <strong>Authorized redirect URIs</strong>, add exactly:
                    <x-copy-value :value="route('sso.callback', 'google')" label="Google redirect URI" />
                </li>
                <li>Click <strong>Create</strong>. Copy the <strong>Client ID</strong> and <strong>Client secret</strong> into <a href="{{ route('admin.sign-in') }}">Admin → Sign-in</a>, under Google, and save.</li>
                <li>Sign out and try <strong>Continue with Google</strong> on the sign-in page.</li>
            </ol>
            <p class="mt-3 text-sm text-(--muted)">If Google says <em>redirect_uri_mismatch</em>, the address above and the one in the console differ, often by http/https or a trailing slash.</p>
        </section>

        <section id="github" aria-labelledby="github-h">
            <h2 id="github-h" class="text-xl font-semibold">GitHub sign-in <span class="text-base font-normal text-(--muted)">(optional)</span></h2>
            <p class="mt-1 text-sm text-(--muted)">Takes about 2 minutes. Handy for developers who would rather not use Google.</p>
            <ol class="guide">
                <li>On GitHub, go to <a href="https://github.com/settings/developers" target="_blank" rel="noopener noreferrer">Settings → Developer settings → OAuth Apps</a> and click <strong>New OAuth App</strong>. For an organisation, use the organisation's settings instead.</li>
                <li>
                    Fill in an application name, and as <strong>Homepage URL</strong>:
                    <x-copy-value :value="$appUrl" label="homepage URL" />
                </li>
                <li>
                    As <strong>Authorization callback URL</strong>, exactly:
                    <x-copy-value :value="route('sso.callback', 'github')" label="GitHub callback URL" />
                </li>
                <li>Leave <strong>Enable Device Flow</strong> off and click <strong>Register application</strong>.</li>
                <li>Copy the <strong>Client ID</strong>. Click <strong>Generate a new client secret</strong> and copy it straight away: GitHub shows it only once.</li>
                <li>In <a href="{{ route('admin.sign-in') }}">Admin → Sign-in</a>, switch on GitHub, paste both values and save.</li>
            </ol>
        </section>

        <section id="nvd" aria-labelledby="nvd-h">
            <h2 id="nvd-h" class="text-xl font-semibold">NVD API key <span class="text-base font-normal text-(--muted)">(optional, recommended)</span></h2>
            <p class="mt-1 text-sm text-(--muted)">
                Free. NVD is where the issues for nginx, Apache and the language runtimes come from. Without a key the site
                waits 6 seconds between NVD requests; with one, 0.6 seconds.
            </p>
            <ol class="guide">
                <li>Open the <a href="https://nvd.nist.gov/developers/request-an-api-key" target="_blank" rel="noopener noreferrer">NVD API key request form</a>.</li>
                <li>Enter your organisation name (your own name is fine), an email address you can read, and choose the organisation type. Accept the terms of use and submit.</li>
                <li>NVD sends an email with a link. Open it within 7 days to activate the key; the key is shown on that page. Copy it.</li>
                <li>Paste it below and save, then click <strong>Test key</strong>.</li>
            </ol>

            <form wire:submit="saveNvdKey" class="card mt-4 space-y-3">
                <p class="text-sm">
                    @if ($nvdSource === 'site')
                        A key is saved on this site.
                    @elseif ($nvdSource === 'env')
                        A key is set in .env (NVD_API_KEY). A key saved here replaces it.
                    @else
                        No key yet.
                    @endif
                </p>
                <x-field label="NVD API key" name="nvdKey" :hint="$nvdSource ? 'Leave blank to keep the current key.' : null">
                    <input id="nvdKey" type="password" wire:model="nvdKey" class="input" autocomplete="off" spellcheck="false">
                </x-field>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn-primary">Save key</button>
                    <button type="button" wire:click="testNvdKey" class="btn-secondary" wire:loading.attr="disabled" wire:target="testNvdKey">Test key</button>
                    @if ($nvdSource === 'site')
                        <button type="button" wire:click="removeNvdKey" wire:confirm="Remove the NVD API key?" class="text-sm underline">Remove key</button>
                    @endif
                    <span wire:loading wire:target="testNvdKey" class="text-sm text-(--muted)">Asking NVD…</span>
                    @if ($nvdTest === 'ok')
                        <span role="status" class="text-sm font-medium text-green-700 dark:text-green-400">NVD accepted the key.</span>
                    @endif
                </div>
            </form>
        </section>
    </div>
</div>
