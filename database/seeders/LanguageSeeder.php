<?php

namespace Database\Seeders;

use App\Enums\LanguageKind;
use App\Models\Language;
use Illuminate\Database\Seeder;

/**
 * The languages and servers we collect issues for. Runs on every start (see docker/entrypoint.sh)
 * but only adds missing ones, so edits made in Admin → Languages are kept.
 */
class LanguageSeeder extends Seeder
{
    public const LANGUAGES = [
        ['php', 'PHP', LanguageKind::Language, ['Packagist'], ['cpe:2.3:a:php:php']],
        ['python', 'Python', LanguageKind::Language, ['PyPI'], ['cpe:2.3:a:python:python']],
        ['javascript', 'JavaScript / Node.js', LanguageKind::Language, ['npm'], ['cpe:2.3:a:nodejs:node.js']],
        ['go', 'Go', LanguageKind::Language, ['Go'], ['cpe:2.3:a:golang:go']],
        ['rust', 'Rust', LanguageKind::Language, ['crates.io'], ['cpe:2.3:a:rust-lang:rust']],
        ['java', 'Java', LanguageKind::Language, ['Maven'], ['cpe:2.3:a:oracle:openjdk']],
        ['dotnet', '.NET / C#', LanguageKind::Language, ['NuGet'], ['cpe:2.3:a:microsoft:.net']],
        ['ruby', 'Ruby', LanguageKind::Language, ['RubyGems'], ['cpe:2.3:a:ruby-lang:ruby']],
        ['elixir', 'Elixir / Erlang', LanguageKind::Language, ['Hex'], ['cpe:2.3:a:erlang:erlang\/otp']],
        ['dart', 'Dart / Flutter', LanguageKind::Language, ['Pub'], []],
        ['nginx', 'nginx', LanguageKind::Server, [], ['cpe:2.3:a:f5:nginx', 'cpe:2.3:a:nginx:nginx']],
        ['apache', 'Apache HTTP Server', LanguageKind::Server, [], ['cpe:2.3:a:apache:http_server']],
    ];

    public function run(): void
    {
        foreach (self::LANGUAGES as [$slug, $name, $kind, $ecosystems, $cpes]) {
            Language::query()->firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'kind' => $kind,
                'enabled' => true,
                'osv_ecosystems' => $ecosystems,
                'nvd_cpes' => $cpes,
            ]);
        }
    }
}
