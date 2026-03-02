#!/usr/bin/env bash
set -e
echo "Running tests (PostgreSQL at DB_HOST=${DB_HOST:-db}). Use --parallel to run in parallel (may hang with some tests)."
exec php -d memory_limit=512M -d zend.max_allowed_stack_size=512M artisan test "$@"
