<?php

namespace App\Sources;

use App\Jobs\SyncFeed;
use App\Models\Language;
use App\Sources\Nvd\NvdSource;
use App\Sources\Osv\OsvSource;
use InvalidArgumentException;

/** Queues one SyncFeed per enabled language and feed of a source. */
class SyncDispatcher
{
    public const SOURCES = [
        'osv' => OsvSource::class,
        'nvd' => NvdSource::class,
    ];

    /** @return int number of jobs queued */
    public function dispatch(string $source, ?string $languageSlug = null): int
    {
        $class = self::SOURCES[$source] ?? throw new InvalidArgumentException("Unknown source \"$source\".");
        $instance = app($class);
        $count = 0;

        Language::query()->enabled()
            ->when($languageSlug, fn ($q) => $q->where('slug', $languageSlug))
            ->orderBy('id')
            ->each(function (Language $language) use ($class, $instance, &$count) {
                foreach ($instance->feeds($language) as $feed) {
                    SyncFeed::dispatch($class, $language->id, $feed);
                    $count++;
                }
            });

        return $count;
    }
}
