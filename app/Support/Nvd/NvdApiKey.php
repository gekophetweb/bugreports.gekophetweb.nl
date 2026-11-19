<?php

namespace App\Support\Nvd;

use App\Support\Settings;

/** The NVD API key: the one saved in Admin → Setup wins over NVD_API_KEY in .env. */
class NvdApiKey
{
    public static function get(): ?string
    {
        $key = Settings::get('nvd.api_key') ?: config('issues.nvd_api_key');

        return filled($key) ? (string) $key : null;
    }

    /** @return 'site'|'env'|null */
    public static function source(): ?string
    {
        return match (true) {
            filled(Settings::get('nvd.api_key')) => 'site',
            filled(config('issues.nvd_api_key')) => 'env',
            default => null,
        };
    }
}
