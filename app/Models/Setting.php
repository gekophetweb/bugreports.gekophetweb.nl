<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One runtime setting (mail, SSO, setup token). Read and write through App\Support\Settings,
 * which caches them and encrypts secrets.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value', 'encrypted'];

    protected function casts(): array
    {
        return ['encrypted' => 'boolean'];
    }
}
