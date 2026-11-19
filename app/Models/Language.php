<?php

namespace App\Models;

use App\Enums\LanguageKind;
use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A programming language or web server we collect issues for. osv_ecosystems are OSV ecosystem names
 * (e.g. "Packagist"); nvd_cpes are NVD virtualMatchString CPEs for the runtime or server itself.
 */
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'name', 'kind', 'enabled', 'osv_ecosystems', 'nvd_cpes'];

    protected function casts(): array
    {
        return [
            'kind' => LanguageKind::class,
            'enabled' => 'boolean',
            'osv_ecosystems' => 'array',
            'nvd_cpes' => 'array',
        ];
    }

    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }
}
