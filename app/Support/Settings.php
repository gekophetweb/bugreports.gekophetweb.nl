<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Runtime settings the superadmin changes in the website (mail, SSO), stored in the
 * settings table. Secrets are encrypted with APP_KEY. All values are cached together
 * under one key and the cache is dropped on every write.
 */
class Settings
{
    public const CACHE_KEY = 'settings.all';

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function many(array $keys): array
    {
        $all = self::all();

        return array_combine($keys, array_map(fn (string $key) => $all[$key] ?? null, $keys));
    }

    public static function set(string $key, mixed $value, bool $secret = false): void
    {
        if ($value === null) {
            Setting::query()->whereKey($key)->delete();
        } else {
            $value = (string) $value;
            Setting::query()->updateOrCreate(['key' => $key], [
                'value' => $secret ? Crypt::encryptString($value) : $value,
                'encrypted' => $secret,
            ]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private static function all(): array
    {
        $rows = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->get(['key', 'value', 'encrypted'])
            ->map(fn (Setting $s) => [$s->key, $s->value, $s->encrypted])
            ->all());

        $values = [];
        foreach ($rows as [$key, $value, $encrypted]) {
            $values[$key] = $encrypted ? Crypt::decryptString($value) : $value;
        }

        return $values;
    }
}
