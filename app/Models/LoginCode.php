<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A one-time sign-in code sent by email: 6 digits, valid 10 minutes, 5 attempts, stored as an HMAC.
 * Requesting a new code replaces the previous one.
 */
class LoginCode extends Model
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['email', 'code_hash', 'attempts', 'expires_at', 'consumed_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    public static function issue(string $email): string
    {
        $email = mb_strtolower(trim($email));
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        static::query()->where('email', $email)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        static::query()->create([
            'email' => $email,
            'code_hash' => self::hash($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        return $code;
    }

    public static function verify(string $email, string $code): bool
    {
        $email = mb_strtolower(trim($email));
        $login = static::query()->where('email', $email)->whereNull('consumed_at')->latest('id')->first();

        if (! $login || $login->expires_at->isPast() || $login->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! hash_equals($login->code_hash, self::hash(trim($code)))) {
            $login->increment('attempts');

            return false;
        }

        // Only the request that flips consumed_at wins, so a code cannot be used twice in parallel.
        return static::query()->whereKey($login->id)->whereNull('consumed_at')->update(['consumed_at' => now()]) === 1;
    }

    private static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
