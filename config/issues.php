<?php

return [

    // How far back the first import of a feed goes.
    'backfill_days' => (int) env('SYNC_BACKFILL_DAYS', 90),

    // OSV "MAL-" ids (malicious packages) run to hundreds of thousands; skipped unless switched on.
    'include_malware' => (bool) env('SYNC_INCLUDE_MALWARE', false),

    // Each run re-reads this many minutes before the cursor, for records that reach OSV's index or NVD's
    // API late with an older timestamp. OSV records whose stored copy is already up to date are not fetched.
    'lookback_minutes' => (int) env('SYNC_LOOKBACK_MINUTES', 120),

    // OSV records fetched per run at most; a large first import catches up over several runs.
    'osv_batch' => (int) env('SYNC_OSV_BATCH', 1500),

    // Optional NVD API key: 50 instead of 5 requests per 30 seconds.
    'nvd_api_key' => env('NVD_API_KEY'),

    // Optional GitHub token for repository security advisories: 5000 instead of 60 requests an hour.
    'github_token' => env('GITHUB_TOKEN'),

    // Only issues first seen by us and published this many days ago or less are announced as new.
    'announce_days' => 7,

    // Changes to Critical issues are emailed to every language subscriber only for issues this recent.
    'critical_update_window_days' => (int) env('CRITICAL_UPDATE_WINDOW_DAYS', 90),

];
