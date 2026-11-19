# bugreports.gekophetweb.nl

Laravel 13 + Livewire 4 + Pest 4, PostgreSQL 17, Redis 8, Octane/FrankenPHP, Docker Compose.

- Local PHP is too old; run everything in Docker:
  - tests: `./docker/test.sh [pest args]` (PHP 8.5 + throwaway Postgres, `docker-compose.test.yml`)
  - artisan/composer: `./docker/artisan.sh make:model Foo`, `./docker/artisan.sh composer require x`
- Gitflow: work on `feature/*` off `develop`; `main` holds releases.
- Design: `docs/superpowers/specs/2026-11-19-bugreports-design.md`; plan: `docs/superpowers/plans/2026-11-19-bugreports.md`.
- Commit messages: conventional, no co-author trailers.
