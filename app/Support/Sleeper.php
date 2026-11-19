<?php

namespace App\Support;

/** Waits between rate-limited API requests. Swapped out in tests. */
class Sleeper
{
    public function sleep(float $seconds): void
    {
        usleep((int) round($seconds * 1_000_000));
    }
}
