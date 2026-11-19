<div class="mx-auto max-w-sm py-12">
    <h1 class="text-2xl font-semibold">Sign in</h1>

    @if ($providers)
        <div class="mt-6 space-y-2">
            @foreach ($providers as $slug => $label)
                <a href="{{ route('sso.redirect', $slug) }}" class="btn-secondary w-full">Continue with {{ $label }}</a>
            @endforeach
        </div>
        <p class="my-6 text-center text-xs uppercase tracking-wide text-zinc-500">or get a code by email</p>
    @endif

    @if ($step === 'email')
        <form wire:submit="requestCode" class="mt-6 space-y-4">
            <x-field label="Email" name="email">
                <input id="email" type="email" wire:model="email" autocomplete="email" class="input" required autofocus>
            </x-field>
            <button type="submit" class="btn-primary w-full">Email me a code</button>
        </form>
    @else
        <form wire:submit="verifyCode" class="mt-6 space-y-4">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">We sent a 6-digit code to <strong>{{ $email }}</strong>. It is valid for 10 minutes.</p>
            <x-field label="Code" name="code">
                <input id="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" wire:model="code"
                       autocomplete="one-time-code" class="input tracking-[0.5em]" required autofocus>
            </x-field>
            <button type="submit" class="btn-primary w-full">Sign in</button>
            <button type="button" wire:click="back" class="w-full text-sm text-zinc-600 underline dark:text-zinc-400">Use another email</button>
        </form>
    @endif
</div>
