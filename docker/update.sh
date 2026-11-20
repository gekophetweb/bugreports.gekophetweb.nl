#!/usr/bin/env bash
# Updates this installation to the newest release: backs up the database, pulls, rebuilds and restarts.
# Does nothing when there is no new release, so it is safe to run from cron:
#
#   ./docker/update.sh            update if origin/main has something new
#   ./docker/update.sh --check    only say whether an update is available (exit 10 if so)
#   ./docker/update.sh --force    back up, rebuild and restart even without a new release
#
# Settings (environment): UPDATE_BRANCH (main), BACKUP_DIR (./backups), KEEP_BACKUPS (14), HEALTH_TIMEOUT (180 s).
set -euo pipefail
cd "$(dirname "$0")/.."

BRANCH="${UPDATE_BRANCH:-main}"
BACKUP_DIR="${BACKUP_DIR:-./backups}"
KEEP_BACKUPS="${KEEP_BACKUPS:-14}"
HEALTH_TIMEOUT="${HEALTH_TIMEOUT:-180}"
MODE="${1:-update}"

log() { echo "$(date '+%F %T') $*"; }
fail() { log "ERROR: $*" >&2; exit 1; }
version() { git describe --tags --always "$1" 2>/dev/null || git rev-parse --short "$1"; }

case "$MODE" in update|--check|--force) ;; *) fail "Unknown option \"$MODE\". Use --check or --force." ;; esac

# One update at a time (cron and a manual run could overlap).
if ! mkdir .update.lock 2>/dev/null; then
  log "Another update is running (remove .update.lock if it is stale)."
  exit 0
fi
trap 'rmdir .update.lock 2>/dev/null || true' EXIT

[ "$(git rev-parse --abbrev-ref HEAD)" = "$BRANCH" ] || fail "This checkout is on $(git rev-parse --abbrev-ref HEAD), not $BRANCH."
[ -z "$(git status --porcelain --untracked-files=no)" ] || fail "There are local changes to tracked files; commit or discard them first."

git fetch --quiet --tags origin "$BRANCH"
current=$(git rev-parse HEAD)
latest=$(git rev-parse "origin/$BRANCH")

if [ "$current" = "$latest" ]; then
  [ "$MODE" = "--force" ] || { log "Up to date ($(version HEAD))."; exit 0; }
elif ! git merge-base --is-ancestor "$current" "$latest"; then
  fail "origin/$BRANCH is not ahead of this checkout (local commits?); not updating."
elif [ "$MODE" = "--check" ]; then
  log "Update available: $(version HEAD) -> $(version "origin/$BRANCH")."
  exit 10
fi
[ "$MODE" != "--check" ] || { log "Up to date ($(version HEAD))."; exit 0; }

log "Updating $(version HEAD) -> $(version "origin/$BRANCH")."

# 1. Back up the database, so a bad migration can be undone.
mkdir -p "$BACKUP_DIR"
backup="$BACKUP_DIR/bugreports-$(date +%Y%m%d-%H%M%S)-$(git rev-parse --short HEAD).sql.gz"
docker compose exec -T db sh -c 'pg_dump --clean --if-exists -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > "$backup" \
  || { rm -f "$backup"; fail "Database backup failed; nothing was changed."; }
log "Database backed up to $backup."
# shellcheck disable=SC2012 # our own file names, newest first
ls -1t "$BACKUP_DIR"/bugreports-*.sql.gz 2>/dev/null | tail -n +"$((KEEP_BACKUPS + 1))" | while read -r old; do rm -f -- "$old"; done

# 2. Pull and rebuild. The app container migrates the database when it starts.
git merge --quiet --ff-only "origin/$BRANCH"
docker compose up -d --build --remove-orphans

# 3. Wait until the app is healthy.
app=$(docker compose ps -q app)
for _ in $(seq 1 "$((HEALTH_TIMEOUT / 5))"); do
  health=$(docker inspect --format '{{.State.Health.Status}}' "$app" 2>/dev/null || echo unknown)
  [ "$health" = "healthy" ] && break
  sleep 5
done
if [ "${health:-}" != "healthy" ]; then
  log "ERROR: the app is not healthy after ${HEALTH_TIMEOUT}s (status: ${health:-unknown})." >&2
  log "Logs: docker compose logs --tail=100 app" >&2
  log "Roll back: git checkout $current && docker compose up -d --build" >&2
  log "and, if a migration broke the data: gunzip -c $backup | docker compose exec -T db psql -U <DB_USERNAME> <DB_DATABASE>" >&2
  exit 1
fi

docker image prune -f >/dev/null
log "Updated to $(version HEAD); the app is healthy."
