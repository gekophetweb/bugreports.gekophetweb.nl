<?php

namespace App\Support;

/** Keeps only http(s) links from source data, so no javascript: or data: URL ever becomes an href. */
class SafeUrls
{
    /**
     * @param  iterable<mixed>  $urls
     * @return list<string>
     */
    public static function filter(iterable $urls): array
    {
        $safe = [];
        foreach ($urls as $url) {
            if (is_string($url) && preg_match('#^https?://[^\s]+$#i', trim($url))) {
                $safe[trim($url)] = true;
            }
        }

        return array_keys($safe);
    }
}
