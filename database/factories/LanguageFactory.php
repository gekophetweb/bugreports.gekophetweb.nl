<?php

namespace Database\Factories;

use App\Enums\LanguageKind;
use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Language> */
class LanguageFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'kind' => LanguageKind::Language,
            'enabled' => true,
            'osv_ecosystems' => [],
            'nvd_cpes' => [],
            'github_repos' => [],
        ];
    }
}
