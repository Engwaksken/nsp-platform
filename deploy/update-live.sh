#!/usr/bin/env bash
# Run ON THE LIVE SERVER (cPanel Terminal or SSH) inside the app folder, e.g. ~/nsp.
# Pulls the latest self-contained release (built by deploy/build-release.sh) and applies it.
# No Composer, Node.js or package downloads are needed: vendor/ comes with the release branch.
set -euo pipefail
cd "$(dirname "$0")/.."

BRANCH="${RELEASE_BRANCH:-production}"
PHP="${PHP_BIN:-php}"

echo "==> Maintenance mode on"
"$PHP" artisan down --retry=60 || true
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Pulling $BRANCH"
git fetch --quiet origin "$BRANCH"
git merge --ff-only "origin/$BRANCH"

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
