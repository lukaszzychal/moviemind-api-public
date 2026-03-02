#!/usr/bin/env bash
# Backup PostgreSQL database via Docker Compose.
# Usage: backup-db.sh <compose-file> <backup-dir> [retention-days]
# Example: ./scripts/backup-db.sh docker-compose.production.yml /var/backups/moviemind 14
# Requires: docker compose, api/.env with DB_USERNAME and DB_DATABASE (or set in environment).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="${1:-}"
BACKUP_DIR="${2:-}"
RETENTION_DAYS="${3:-14}"

if [[ -z "$COMPOSE_FILE" || -z "$BACKUP_DIR" ]]; then
  echo "Usage: $0 <compose-file> <backup-dir> [retention-days]"
  echo "  compose-file   e.g. docker-compose.production.yml or docker-compose.staging.yml"
  echo "  backup-dir     directory to write backup files (created if missing)"
  echo "  retention-days optional; default 14. Delete backups older than this."
  exit 1
fi

cd "$PROJECT_ROOT"

# Load DB_* from api/.env if not set
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

mkdir -p "$BACKUP_DIR"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_FILE="$BACKUP_DIR/backup-$TIMESTAMP.sql"

echo "Backing up $DB_DATABASE to $BACKUP_FILE ..."
if ! docker compose -f "$COMPOSE_PATH" exec -T db pg_dump -U "$DB_USERNAME" "$DB_DATABASE" > "$BACKUP_FILE"; then
  echo "Error: pg_dump failed."
  exit 1
fi

if [[ -s "$BACKUP_FILE" ]]; then
  gzip -f "$BACKUP_FILE"
  echo "Compressed to ${BACKUP_FILE}.gz"
else
  echo "Error: backup file is empty."
  exit 1
fi

# Rotate: remove backups older than RETENTION_DAYS
if [[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]] && [[ "$RETENTION_DAYS" -gt 0 ]]; then
  find "$BACKUP_DIR" -maxdepth 1 -type f \( -name 'backup-*.sql' -o -name 'backup-*.sql.gz' \) -mtime +"$RETENTION_DAYS" -delete
  echo "Removed backups older than $RETENTION_DAYS days."
fi

echo "Backup done: ${BACKUP_FILE}.gz"
