#!/usr/bin/env bash
# Build a self-contained release for shared (cPanel) hosting and push it to the `production` branch.
#
# The release branch holds the application *plus* the production `vendor/` folder (no dev packages),
# so the live server only runs `git pull` — it never needs Composer, Node.js or internet access to
# package registries. Development branches keep ignoring `vendor/` as usual.
#
# Usage (from a clean checkout, in Git Bash or any POSIX shell):
#   deploy/build-release.sh              # release the current HEAD
#   deploy/build-release.sh main         # release another branch, tag or commit
#   RELEASE_BRANCH=staging deploy/build-release.sh
#   NO_PUSH=1 deploy/build-release.sh    # build and commit locally, don't push
set -euo pipefail

SOURCE="${1:-HEAD}"
BRANCH="${RELEASE_BRANCH:-production}"
REMOTE="${RELEASE_REMOTE:-origin}"

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

if [ -n "$(git status --porcelain --untracked-files=no -- . ':!database/database.sqlite')" ] && [ "$SOURCE" = "HEAD" ]; then
  echo "Note: uncommitted changes are NOT included; the release is built from the last commit." >&2
fi

SHA="$(git rev-parse --verify "$SOURCE^{commit}")"
SHORT="$(git rev-parse --short "$SHA")"
LABEL="$(git rev-parse --abbrev-ref "$SOURCE" 2>/dev/null || echo "$SOURCE")"
WORK="$(mktemp -d)"
TREE="$WORK/release"
trap 'git -C "$ROOT" worktree remove --force "$TREE" >/dev/null 2>&1 || true; rm -rf "$WORK"' EXIT

echo "==> Building release of $LABEL ($SHORT) into branch $BRANCH"
git fetch --quiet "$REMOTE" "$BRANCH" 2>/dev/null || true
git worktree add --quiet --detach "$TREE"
cd "$TREE"
if git rev-parse --verify --quiet "$REMOTE/$BRANCH" >/dev/null; then
  git checkout --quiet -B "$BRANCH" "$REMOTE/$BRANCH"
elif git rev-parse --verify --quiet "refs/heads/$BRANCH" >/dev/null; then
  git checkout --quiet "$BRANCH"
else
  git checkout --quiet --orphan "$BRANCH"
fi

# Replace the whole tree with the source commit (this also drops files deleted since the last release).
git rm -rq --cached --ignore-unmatch . >/dev/null
find . -mindepth 1 -maxdepth 1 ! -name .git -exec rm -rf {} +
git -C "$ROOT" archive "$SHA" | tar -x -C "$TREE"

# Ship vendor/ on this branch only.
sed -i.bak '/^\/vendor$/d' .gitignore && rm -f .gitignore.bak

echo "==> Installing production PHP libraries (composer install --no-dev)"
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --no-scripts --prefer-dist
# Laravel rebuilds bootstrap/cache/packages.php on the server on first request.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

printf '%s\n' "$SHA" > RELEASE
git add -A
if git diff --cached --quiet; then
  echo "==> Nothing changed since the last release; not committing."
  exit 0
fi
git commit --quiet -m "Release $SHORT from $LABEL" -m "Self-contained build for shared hosting: application code plus production vendor/ (composer install --no-dev). Source commit: $SHA"
echo "==> Committed $(git rev-parse --short HEAD) on $BRANCH ($(git ls-files vendor | wc -l | tr -d ' ') vendor files)"

if [ -z "${NO_PUSH:-}" ]; then
  git push --quiet "$REMOTE" "$BRANCH"
  echo "==> Pushed to $REMOTE/$BRANCH. On the server run: deploy/update-live.sh"
fi
