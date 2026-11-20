<?php

namespace App\Models;

use App\Enums\LanguageKind;
use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A programming language or web server we collect issues for. osv_ecosystems are OSV ecosystem names
 * (e.g. "Packagist"); nvd_cpes are NVD virtualMatchString CPEs for the runtime or server itself;
 * github_repos are repositories whose own security advisories are read (e.g. "php/php-src");
 * zero_wire_tags are Cloudflare Radar Zero Wire tags whose briefings belong to it (e.g. "PHP").
 */
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'name', 'kind', 'enabled', 'osv_ecosystems', 'nvd_cpes', 'github_repos', 'zero_wire_tags'];

    protected function casts(): array
    {
        return [
            'kind' => LanguageKind::class,
            'enabled' => 'boolean',
            'osv_ecosystems' => 'array',
            'nvd_cpes' => 'array',
            'github_repos' => 'array',
            'zero_wire_tags' => 'array',
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
