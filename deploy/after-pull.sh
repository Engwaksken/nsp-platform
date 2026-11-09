#!/usr/bin/env bash
# Applies a release that is already on disk: migrations, permissions, caches.
# Runs automatically after `git pull` once deploy/install-hooks.sh has been run on the server,
# and is called by deploy/update-live.sh. Safe to run again at any time.
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP_BIN:-php}"

echo "==> Maintenance mode on"
"$PHP" artisan down --retry=60 || true
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Migrating database"
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=RolesAndPermissionsSeeder --force

echo "==> Rebuilding caches"
"$PHP" artisan optimize:clear
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan view:cache
[ -e public/storage ] || "$PHP" artisan storage:link || echo "   (storage:link failed; see docs/deployment.md)"

echo "==> Live on release $(cat RELEASE 2>/dev/null || git rev-parse --short HEAD)"
