@if (session('status'))
    <div role="status" class="mb-4 rounded-md border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
        {{ session('status') }}
    </div>
@endif
