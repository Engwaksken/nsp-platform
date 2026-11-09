#!/usr/bin/env bash
# Run ONCE on the live server, inside the app folder (e.g. ~/nsp):  bash deploy/install-hooks.sh
# Installs a git post-merge hook so every `git pull` of the production branch
# (terminal or cPanel "Update from Remote") also runs deploy/after-pull.sh:
# migrations, permissions and cache rebuild. Do not install it on development machines.
set -euo pipefail
cd "$(dirname "$0")/.."

HOOK="$(git rev-parse --git-path hooks)/post-merge"
mkdir -p "$(dirname "$HOOK")"
cat > "$HOOK" <<'HOOK'
#!/usr/bin/env bash
# Installed by deploy/install-hooks.sh: apply the release after every pull.
[ -n "${NSP_SKIP_DEPLOY_HOOK:-}" ] && exit 0
cd "$(git rev-parse --show-toplevel)"
mkdir -p storage/logs
bash deploy/after-pull.sh 2>&1 | tee -a storage/logs/deploy.log
HOOK
chmod +x "$HOOK"
chmod +x deploy/*.sh 2>/dev/null || true
echo "Installed $HOOK. From now on, 'git pull' applies the release (log: storage/logs/deploy.log)."
