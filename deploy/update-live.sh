#!/usr/bin/env bash
# Run ON THE LIVE SERVER (cPanel Terminal or SSH) inside the app folder, e.g. ~/nsp.
# Pulls the latest self-contained release (built by deploy/build-release.sh) and applies it.
# No Composer, Node.js or package downloads are needed: vendor/ comes with the release branch.
#
# Once deploy/install-hooks.sh has been run, a plain `git pull` does the same thing,
# so this script is only needed on servers without the hook.
set -euo pipefail
cd "$(dirname "$0")/.."

BRANCH="${RELEASE_BRANCH:-production}"

echo "==> Pulling $BRANCH"
git fetch --quiet origin "$BRANCH"
# The post-merge hook (if installed) would apply the release too; skip it so it runs once.
NSP_SKIP_DEPLOY_HOOK=1 git merge --ff-only "origin/$BRANCH"

bash deploy/after-pull.sh
