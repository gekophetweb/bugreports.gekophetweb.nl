<div class="pb-12">
    <x-admin-nav />
    <h1 class="text-2xl font-semibold">Users</h1>

    <div class="mt-4 max-w-sm">
        <label for="search" class="sr-only">Search users</label>
        <input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Search name or email">
    </div>

    @error('role')
        <p class="mt-4 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <div class="card mt-4 overflow-x-auto p-0">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 text-xs uppercase text-zinc-500 dark:border-zinc-800">
                <tr><th class="px-4 py-2">Name</th><th class="px-4 py-2">Email</th><th class="px-4 py-2">Joined</th><th class="px-4 py-2">Role</th></tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                        <td class="px-4 py-2">{{ $user->name }}</td>
                        <td class="px-4 py-2">{{ $user->email }}</td>
                        <td class="px-4 py-2 text-zinc-500">{{ $user->created_at->format('j M Y') }}</td>
                        <td class="px-4 py-2">
                            <label for="role-{{ $user->id }}" class="sr-only">Role of {{ $user->name }}</label>
                            <select id="role-{{ $user->id }}" class="input py-1" wire:change="setRole({{ $user->id }}, $event.target.value)">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</div>
