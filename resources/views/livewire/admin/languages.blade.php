<div class="pb-12">
    <x-admin-nav />
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-semibold">Languages and servers</h1>
        <button type="button" wire:click="create" class="btn-secondary ml-auto">Add</button>
    </div>
    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
        OSV ecosystems cover packages (e.g. Packagist, PyPI, npm). NVD CPEs cover the runtime or server itself (e.g. cpe:2.3:a:f5:nginx).
    </p>

    <div class="mt-4"><x-status /></div>

    @if ($editing)
        <form wire:submit="save" class="card mt-4 max-w-2xl space-y-4">
            <h2 class="font-semibold">{{ $editingId ? 'Edit '.$form['name'] : 'Add a language or server' }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="Name" name="form.name" class="sm:col-span-2">
                    <input id="form.name" type="text" wire:model="form.name" class="input">
                </x-field>
                <x-field label="Slug" name="form.slug">
                    <input id="form.slug" type="text" wire:model="form.slug" class="input">
                </x-field>
            </div>
            <x-field label="Kind" name="form.kind">
                <select id="form.kind" wire:model="form.kind" class="input">
                    @foreach ($kinds as $kind)
                        <option value="{{ $kind->value }}">{{ ucfirst($kind->value) }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="OSV ecosystems" name="form.osv_ecosystems" hint="Comma separated, exactly as OSV names them.">
                <input id="form.osv_ecosystems" type="text" wire:model="form.osv_ecosystems" class="input">
            </x-field>
            <x-field label="NVD CPEs" name="form.nvd_cpes" hint="Comma separated CPE 2.3 names.">
                <input id="form.nvd_cpes" type="text" wire:model="form.nvd_cpes" class="input">
            </x-field>
            <x-field label="GitHub repositories" name="form.github_repos" hint="Comma separated owner/name, for repositories that publish their own security advisories (e.g. php/php-src).">
                <input id="form.github_repos" type="text" wire:model="form.github_repos" class="input">
            </x-field>
            <x-field label="Zero Wire tags" name="form.zero_wire_tags" hint="Comma separated tags of Cloudflare Radar Zero Wire briefings that belong here (e.g. PHP). Not case sensitive.">
                <input id="form.zero_wire_tags" type="text" wire:model="form.zero_wire_tags" class="input">
            </x-field>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary">Save</button>
                <button type="button" wire:click="cancel" class="btn-secondary">Cancel</button>
            </div>
        </form>
    @endif

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-800">
                <tr>
                    <th class="px-4 py-2">Name</th><th class="px-4 py-2">OSV</th><th class="px-4 py-2">NVD</th><th class="px-4 py-2">GitHub</th><th class="px-4 py-2">Zero Wire</th>
                    <th class="px-4 py-2 text-right">Issues</th><th class="px-4 py-2">Synced</th><th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($languages as $language)
                    <tr wire:key="lang-{{ $language->id }}" class="border-b border-zinc-100 align-top last:border-0 dark:border-zinc-800">
                        <td class="px-4 py-2">{{ $language->name }} <span class="text-xs text-zinc-500">{{ $language->kind->value }}</span></td>
                        <td class="px-4 py-2">{{ implode(', ', $language->osv_ecosystems ?? []) ?: '–' }}</td>
                        <td class="px-4 py-2 break-all">{{ implode(', ', $language->nvd_cpes ?? []) ?: '–' }}</td>
                        <td class="px-4 py-2">{{ implode(', ', $language->github_repos ?? []) ?: '–' }}</td>
                        <td class="px-4 py-2">{{ implode(', ', $language->zero_wire_tags ?? []) ?: '–' }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($language->issues_count) }}</td>
                        <td class="px-4 py-2">
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" @checked($language->enabled) wire:click="toggle({{ $language->id }})" class="size-4">
                                <span class="sr-only">Sync {{ $language->name }}</span>
                            </label>
                        </td>
                        <td class="px-4 py-2 text-right"><button type="button" wire:click="edit({{ $language->id }})" class="underline">Edit</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
