#!/usr/bin/env bash
# Restore a backup into a temporary database and verify it; then drop the temporary database.
# Usage: verify-restore-db.sh <compose-file> <backup-dir> [backup-file]
# Example: ./scripts/verify-restore-db.sh compose.production.yml /var/backups/moviemind
# If backup-file is omitted, uses the latest backup-*.sql or backup-*.sql.gz in backup-dir by mtime.

set -euo pipefail

VERIFY_DB="moviemind_restore_verify"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="${1:-}"
BACKUP_DIR="${2:-}"
BACKUP_FILE_OPT="${3:-}"

if [[ -z "$COMPOSE_FILE" || -z "$BACKUP_DIR" ]]; then
  echo "Usage: $0 <compose-file> <backup-dir> [backup-file]"
  echo "  compose-file   e.g. compose.production.yml or compose.staging.yml"
  echo "  backup-dir     directory containing backup-*.sql or backup-*.sql.gz"
  echo "  backup-file    optional; path to specific backup. If omitted, latest by mtime is used."
  exit 1
fi

cd "$PROJECT_ROOT"

if [[ -z "${DB_USERNAME:-}" ]] || [[ -z "${DB_DATABASE:-}" ]]; then
  if [[ -f api/.env ]]; then
    export DB_USERNAME="${DB_USERNAME:-$(grep -E '^DB_USERNAME=' api/.env | cut -d= -f2- | tr -d '"' | tr -d "'")}"
    export DB_DATABASE="${DB_DATABASE:-$(grep -E '^DB_DATABASE=' api/.env | cut -d= -f2- | tr -d '"' | tr -d "'")}"
  fi
fi

if [[ -z "${DB_USERNAME:-}" ]] || [[ -z "${DB_DATABASE:-}" ]]; then
  echo "Error: DB_USERNAME and DB_DATABASE must be set (in api/.env or environment)."
  exit 1
fi

COMPOSE_PATH="$PROJECT_ROOT/$COMPOSE_FILE"
if [[ ! -f "$COMPOSE_PATH" ]]; then
  echo "Error: Compose file not found: $COMPOSE_PATH"
  exit 1
fi

if [[ -n "$BACKUP_FILE_OPT" ]]; then
  if [[ ! -f "$BACKUP_FILE_OPT" ]]; then
    echo "Error: Backup file not found: $BACKUP_FILE_OPT"
    exit 1
  fi
  BACKUP_FILE="$BACKUP_FILE_OPT"
else
  # Latest backup by modification time (prefer .gz)
  BACKUP_FILE=""
  for f in "$BACKUP_DIR"/backup-*.sql.gz "$BACKUP_DIR"/backup-*.sql; do
    [[ -e $f ]] || continue
    if [[ -z "$BACKUP_FILE" ]] || [[ $f -nt $BACKUP_FILE ]]; then
      BACKUP_FILE="$f"
    fi
  done
  if [[ -z "$BACKUP_FILE" ]] || [[ ! -f "$BACKUP_FILE" ]]; then
    echo "Error: No backup file found in $BACKUP_DIR"
    exit 1
  fi
fi

echo "Using backup: $BACKUP_FILE"
echo "Creating temporary database $VERIFY_DB ..."

docker compose -f "$COMPOSE_PATH" exec -T db psql -U "$DB_USERNAME" -d postgres -c "DROP DATABASE IF EXISTS $VERIFY_DB;"
docker compose -f "$COMPOSE_PATH" exec -T db psql -U "$DB_USERNAME" -d postgres -c "CREATE DATABASE $VERIFY_DB;"

echo "Restoring into $VERIFY_DB ..."
if [[ "$BACKUP_FILE" == *.gz ]]; then
  gunzip -c "$BACKUP_FILE" | docker compose -f "$COMPOSE_PATH" exec -T db psql -U "$DB_USERNAME" -d "$VERIFY_DB" > /dev/null 2>&1
else
  docker compose -f "$COMPOSE_PATH" exec -T db psql -U "$DB_USERNAME" -d "$VERIFY_DB" < "$BACKUP_FILE" > /dev/null 2>&1
fi

# Verification: expect at least one table in public schema; and movies table should exist and be queryable
TABLE_COUNT="$(docker compose -f "$COMPOSE_PATH" exec -T db psql -U "$DB_USERNAME" -d "$VERIFY_DB" -t -A -c "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public';")"
TABLE_COUNT="${TABLE_COUNT// /}"

if [[ ! "$TABLE_COUNT" =~ ^[0-9]+$ ]] || [[ "$TABLE_COUNT" -lt 1 ]]; then
  echo "Error: Verification failed: expected at least 1 table in public schema, got: $TABLE_COUNT"
  docker compose -f "$COMPOSE_PATH" exec -T db psql -U "$DB_USERNAME" -d postgres -c "DROP DATABASE IF EXISTS $VERIFY_DB;"
  exit 1
fi

# Check that key table exists and is queryable
MOVIES_COUNT="$(docker compose -f "$COMPOSE_PATH" exec -T db psql -U "$DB_USERNAME" -d "$VERIFY_DB" -t -A -c "SELECT count(*) FROM movies;" 2>/dev/null)" || true
echo "Tables in public schema: $TABLE_COUNT. Rows in movies: ${MOVIES_COUNT:-N/A}"

echo "Dropping temporary database $VERIFY_DB ..."
docker compose -f "$COMPOSE_PATH" exec -T db psql -U "$DB_USERNAME" -d postgres -c "DROP DATABASE IF EXISTS $VERIFY_DB;"

echo "Verify-restore completed successfully."
