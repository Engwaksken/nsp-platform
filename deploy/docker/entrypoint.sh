#!/bin/sh
# Roles: web (Apache), worker (queue), scheduler (cron replacement), migrate (one-off release step).
set -e
cd /var/www/html
php artisan config:cache
php artisan route:cache
php artisan view:cache
case "$1" in
  web) exec apache2-foreground ;;
  worker) exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600 ;;
  scheduler) exec php artisan schedule:work ;;
  migrate) exec php artisan migrate --force ;;
  *) exec "$@" ;;
esac
