<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/** How far a feed has been synced: the newest "modified" timestamp fully processed. */
class SyncCursor extends Model
{
    protected $fillable = ['source', 'feed', 'cursor'];

    /** OSV timestamps have sub-second precision; without it the newest record is re-fetched every run. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['cursor' => 'immutable_datetime'];
    }

    public static function get(string $source, string $feed): ?CarbonInterface
    {
        return static::query()->where(['source' => $source, 'feed' => $feed])->first()?->cursor;
    }

    public static function put(string $source, string $feed, CarbonInterface $cursor): void
    {
        static::query()->updateOrCreate(['source' => $source, 'feed' => $feed], ['cursor' => $cursor]);
    }
}
