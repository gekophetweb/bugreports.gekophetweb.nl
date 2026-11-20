<?php

namespace Database\Factories;

use App\Enums\Severity;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Issue> */
class IssueFactory extends Factory
{
    public function definition(): array
    {
        $id = 'GHSA-'.implode('-', [fake()->unique()->bothify('????'), fake()->bothify('????'), fake()->bothify('????')]);
        $published = fake()->dateTimeBetween('-60 days', '-1 day');

        return [
            'source' => 'osv',
            'external_id' => $id,
            'aliases' => [],
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'severity' => Severity::High,
            'cvss_score' => 7.5,
            'cvss_vector' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:H',
            'packages' => ['vendor/package'],
            'scope' => 'core',
            'references' => [],
            'source_url' => Issue::sourceUrlFor('osv', $id),
            'published_at' => $published,
            'modified_at' => $published,
            'first_seen_at' => now(),
        ];
    }
}
