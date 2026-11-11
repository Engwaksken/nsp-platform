#!/usr/bin/env bash
# Applies a release that is already on disk: PHP libraries (on a `main` checkout), migrations, permissions, caches.
# Runs automatically after `git pull` once deploy/install-hooks.sh has been run on the server,
# and is called by deploy/update-live.sh. Safe to run again at any time.
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP_BIN:-php}"

echo "==> Maintenance mode on"
"$PHP" artisan down --retry=60 || true
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

# On a checkout of `main` (vendor/ is not in git) install the PHP libraries here.
# Release builds from deploy/build-release.sh ship vendor/ in git, so this is skipped for them.
if ! git ls-files --error-unmatch vendor/autoload.php >/dev/null 2>&1; then
  if [ -n "${COMPOSER_BIN:-}" ]; then COMPOSER="$COMPOSER_BIN"
  elif command -v composer >/dev/null 2>&1; then COMPOSER="composer"
  elif [ -f composer.phar ]; then COMPOSER="$PHP composer.phar"
  elif [ -f "$HOME/composer.phar" ]; then COMPOSER="$PHP $HOME/composer.phar"
  else echo "Composer not found: install it or set COMPOSER_BIN (see docs/deployment.md)" >&2; exit 1
  fi
  echo "==> Installing PHP libraries ($COMPOSER install --no-dev)"
  $COMPOSER install --no-dev --optimize-autoloader --no-interaction --no-progress
fi

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
