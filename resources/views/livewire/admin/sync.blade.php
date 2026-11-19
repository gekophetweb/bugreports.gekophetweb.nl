<div class="pb-12">
    <x-admin-nav />
    <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold">Sync</h1>
        <div class="ml-auto flex gap-2">
            @foreach ($sources as $source)
                <button type="button" wire:click="syncNow('{{ $source }}')" class="btn-secondary">Sync {{ strtoupper($source) }} now</button>
            @endforeach
        </div>
    </div>
    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">OSV is checked every 5 minutes, NVD every 10. Runs are kept for 14 days.</p>

    <div class="mt-4"><x-status /></div>

    <div class="mt-4 flex items-center gap-2 text-sm">
        <label for="status">Show</label>
        <select id="status" wire:model.live="status" class="input w-auto py-1">
            <option value="">All runs</option>
            <option value="failed">Failed</option>
            <option value="ok">Succeeded</option>
            <option value="running">Running</option>
        </select>
    </div>

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-800">
                <tr>
                    <th class="px-4 py-2">Started</th><th class="px-4 py-2">Source</th><th class="px-4 py-2">Feed</th>
                    <th class="px-4 py-2">Status</th><th class="px-4 py-2 text-right">New</th><th class="px-4 py-2 text-right">Changed</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($runs as $run)
                    <tr wire:key="run-{{ $run->id }}" class="border-b border-zinc-100 align-top last:border-0 dark:border-zinc-800">
                        <td class="whitespace-nowrap px-4 py-2 text-zinc-500">{{ $run->started_at->inDisplayTz()->format('j M H:i') }}</td>
                        <td class="px-4 py-2 uppercase">{{ $run->source }}</td>
                        <td class="px-4 py-2">
                            <span class="break-all">{{ $run->feed }}</span>
                            @if ($run->language)<span class="text-zinc-500"> · {{ $run->language->name }}</span>@endif
                            @if ($run->error)
                                <p class="mt-1 text-red-600 dark:text-red-400">{{ $run->error }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <span @class([
                                'rounded px-2 py-0.5 text-xs font-medium',
                                'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' => $run->status === 'ok',
                                'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' => $run->status === 'failed',
                                'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' => $run->status === 'running',
                            ])>{{ $run->status }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">{{ $run->created_count }}</td>
                        <td class="px-4 py-2 text-right">{{ $run->updated_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-zinc-500">No sync runs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $runs->links() }}</div>

    @if ($cursors->isNotEmpty())
        <h2 class="mt-8 text-lg font-semibold">Synced up to</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($cursors as $cursor)
                <li><span class="uppercase">{{ $cursor->source }}</span> · <span class="break-all">{{ $cursor->feed }}</span> · {{ $cursor->cursor?->inDisplayTz()->format('j M Y H:i') }}</li>
            @endforeach
        </ul>
    @endif
</div>
