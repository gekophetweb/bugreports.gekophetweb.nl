#!/bin/sh
# Run artisan or composer in the dev PHP container: ./docker/artisan.sh make:model Foo  |  ./docker/artisan.sh composer require x
cd "$(dirname "$0")/.." || exit 1
if [ "$1" = "composer" ]; then shift; set -- composer "$@"; else set -- php artisan "$@"; fi
exec env MSYS_NO_PATHCONV=1 docker compose -f docker-compose.test.yml run --rm -T tests "$@"
