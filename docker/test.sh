#!/bin/sh
# Run the test suite in Docker (PHP 8.5 + Postgres 17): ./docker/test.sh [pest args]
# The test database container keeps running between runs; stop it with:
#   docker compose -f docker-compose.test.yml down
cd "$(dirname "$0")/.." || exit 1
exec env MSYS_NO_PATHCONV=1 docker compose -f docker-compose.test.yml run --rm -T tests php vendor/bin/pest --colors=never "$@"
