#!/usr/bin/env bash
# Optional: let the server pick up new releases by itself, with no pull at all.
# Add a cron job (cPanel > Cron Jobs), for example every 5 minutes:
#   */5 * * * * cd ~/nsp && bash deploy/auto-update.sh >> storage/logs/deploy.log 2>&1
# It only acts when the production branch on GitHub has a new commit.
set -euo pipefail
cd "$(dirname "$0")/.."

BRANCH="${RELEASE_BRANCH:-production}"
LOCK="storage/framework/deploy.lock"

# One run at a time (a slow migration must not overlap the next cron run).
mkdir -p storage/framework
exec 9>"$LOCK"
if command -v flock >/dev/null 2>&1; then flock -n 9 || exit 0; fi

git fetch --quiet origin "$BRANCH"
[ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$BRANCH")" ] && exit 0

echo "==> $(date '+%Y-%m-%d %H:%M:%S') new release found on $BRANCH"
bash deploy/update-live.sh
