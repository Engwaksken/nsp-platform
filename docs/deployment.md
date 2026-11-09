# Deployment (draft: not yet used)

Nothing has been deployed. This is the intended procedure for staging and then production. Production domains are decision D9.

## Hosting requirements
- PHP 8.2+ with these extensions: ctype, curl, dom, fileinfo, filter, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip.
- MySQL 8 (or MariaDB 10.6+) with utf8mb4.
- HTTPS (Let's Encrypt from cPanel AutoSSL is enough).
- No Node.js is needed. CSS and JS are plain files in `public/`.
- Cron, every minute.

## Releases for shared hosting (`production` branch)
The live server never runs Composer or Node.js. Every library is installed on the development machine and shipped in git:

- Development branches (`main`, `owner-alignment`, …) ignore `vendor/` as usual.
- `deploy/build-release.sh` (run locally) copies a commit into the `production` branch, runs `composer install --no-dev --optimize-autoloader` there, commits the code **with** `vendor/`, and pushes it.
- The server only pulls `production` and runs `deploy/update-live.sh` (migrate, seed permissions, rebuild caches).
- Fonts (Poppins, Outfit) are self-hosted in `public/fonts`; CSS and JS are plain files in `public/`. Pages make no requests to third-party CDNs.

```
# On your computer, after committing:
deploy/build-release.sh            # releases HEAD; or: deploy/build-release.sh main

# On the server (cPanel Terminal or SSH), in ~/nsp:
bash deploy/update-live.sh
```
If the host has no terminal, use cPanel **Git Version Control** to pull the `production` branch, then run the commands from `deploy/update-live.sh` through cPanel Cron Jobs once.

## First install (cPanel)
1. Create the database and user in cPanel. **Never reuse the IMS database or its user.**
2. Clone the release branch to a folder **outside** `public_html`, for example `~/nsp`: `git clone -b production https://github.com/Engwaksken/nsp-platform.git ~/nsp` (for a private repository use a read-only deploy key or a fine-grained token; cPanel **Git Version Control** can do the clone too). It already contains `vendor/`; never upload `.env` or `database/database.sqlite`.
3. Point the subdomain's document root to `~/nsp/public`. If cPanel can't, symlink `public_html/nsp` → `~/nsp/public`.
4. In `~/nsp` (no `composer install` needed):
   ```
   cp .env.example .env      # then edit: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, MAIL_*
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force          # roles, permissions, draft placeholder pages
   php artisan storage:link
   php artisan nsp:create-admin you@hive.example   # asks for the password twice; never pass it on the command line
   # Forgot it or mistyped it? An existing account keeps its password unless you add --reset-password:
   # php artisan nsp:create-admin you@hive.example --reset-password
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
5. The first admin signs in and must set up two-factor sign-in straight away. Keep `NSP_MFA_ENFORCE=true` (the default) on every server. Password reset emails need working `MAIL_*` settings, so send yourself a test reset before go-live.
6. Production `.env` must set:
   - `SESSION_SECURE_COOKIE=true`
   - `SESSION_ENCRYPT=true`
   - `MAIL_MAILER=smtp`
   - `QUEUE_CONNECTION=database`
   - `LOG_LEVEL=warning`

## Cron (no queue worker on shared hosting)
Add one cron job:
```
* * * * * cd ~/nsp && php artisan schedule:run >> /dev/null 2>&1
```
`routes/console.php` already schedules `queue:work --stop-when-empty` every minute, so queued mail (contact notifications) goes out within about a minute. If the host gives SSH access and supervisor, run a real `queue:work` worker instead and remove that schedule (decision D10).

## Updates
1. Back up first: `mysqldump` the NSP database and archive `storage/app/public` and `storage/app/private`.
2. Locally run `deploy/build-release.sh`, then get the new `production` commit onto the server in one of these ways:
   - **Just pull (recommended).** Once, on the server, run `bash deploy/install-hooks.sh`. After that, every `git pull` (terminal, or cPanel **Git Version Control → Update from Remote**) also runs `deploy/after-pull.sh` automatically, with output in `storage/logs/deploy.log`.
   - **Fully automatic.** Add a cron job `*/5 * * * * cd ~/nsp && bash deploy/auto-update.sh >> storage/logs/deploy.log 2>&1`. Every 5 minutes it checks GitHub and, only when `production` has a new commit, pulls and applies it. Nothing to do on the server after a release.
   - **Manually.** `bash deploy/update-live.sh` pulls and applies in one step (works without the hook).

   Applying a release (`deploy/after-pull.sh`) puts the site in maintenance mode, runs `php artisan migrate --force` and `php artisan db:seed --class=RolesAndPermissionsSeeder --force` (safe to repeat; it adds any new permissions), rebuilds the config, route and view caches, and brings the site back up. It is safe to run again by hand if a step failed.
3. The deployed source commit is in the `RELEASE` file at the app root.

Data-room files (`storage/app/private/data-room`) and candidate CVs (`storage/app/private/talent`) live (the private `local` disk). Include that folder in backups; never link it into `public`.

**Rollback:** `git reset --hard <previous production commit>` (see `git log --oneline -5`), restore the database dump if a migration ran, then clear and rebuild the caches.

## After deploying
- Set `NSP_BACKUP_PASSWORD` (store it in the password manager), run `php artisan nsp:backup` once, and arrange the daily off-server copy (`operations-runbook.md` §3).
- Set `NSP_HEALTH_TOKEN` and point an uptime monitor at `/health` (`operations-runbook.md` §2).
- `https://<domain>/up` returns 200.
- `/robots.txt` shows the production rules (`APP_ENV=production`).
- Response headers include `Content-Security-Policy` and `Strict-Transport-Security`.
- Sign in as the admin and change branding. Send a test contact message and confirm it is delivered.

## Enabling single sign-on later
1. Register NSP as a confidential client at the IdP with the redirect URI `https://<domain>/auth/oidc/callback`.
2. Set the `OIDC_*` variables and `NSP_OIDC_ENABLED=true` (or switch the flag on in the admin area).
3. Rebuild the config cache.
4. Test sign-in on staging first.
5. Only after SSO works, consider turning off local login. The admin screen prevents turning off both.

## Connecting Hive IMS (Phase 3)
Do this on staging first. The full rules are in `integration-contract-v1.md`.
1. On the NSP server, create the client:
   `php artisan nsp:api-client ims-live --name="Hive IMS" --redirect-uri=https://ims.hivecolab.com/nsp-callback --webhook-url=https://ims.hivecolab.com/nsp-webhook`
   It prints `NSP_CLIENT_ID`, `NSP_CLIENT_SECRET` and `NSP_WEBHOOK_SECRET` once. Put them in the IMS `.env` straight away and do not store them anywhere else.
2. In the NSP `.env`, set `NSP_IMS_INTEGRATION=true` and `IMS_URL=https://ims.hivecolab.com`. Rebuild the config cache.
3. In the IMS `.env`, set `NSP_INTEGRATION_ENABLED=true`, `NSP_BASE_URL=https://<nsp domain>` and `NSP_REDIRECT_URI` to the same callback address as in step 1. Add the IMS outbox cron job.
4. Test it: apply in IMS with "Prefill from my Hive NSP profile", check that the application appears in NSP under Applications & programs, then turn sharing off in NSP and check that IMS marks the link as revoked.
5. Optional, existing startups: export `application_id, startup_name, registration_number, website` from IMS and run `php artisan registry:import-ims export.csv`. Read the dry-run report before adding `--apply`.
6. To rotate secrets later, run `php artisan nsp:api-client ims-live --rotate` and update the IMS `.env` at the same time.

## Enabling the AI assistant (only after legal approval, decision D13)
1. Put `ANTHROPIC_API_KEY=...` in the server's `.env` (never in git), and set `NSP_AI_LEGAL_APPROVED=true` once the review is signed.
2. Run `php artisan config:cache`, then switch on **AI assistant** under **Admin › Features**.
3. To stop it at once, set `NSP_AI_LEGAL_APPROVED=false` (or switch the flag off) and run `php artisan config:cache` again.

## Containers (owner alignment, directive §4) — not yet built or runtime-verified
`Dockerfile`, `docker-compose.yml` and `deploy/docker/` describe one stateless image with four roles: `web` (Apache, liveness check on `/up`), `worker` (`queue:work`), `scheduler` (`schedule:work`, replaces cron) and `migrate` (one-off release step). `/up` is used because the token-protected `/health` endpoint intentionally returns 404 when no health token is configured. Docker was not available on the development machine, so **the image has not been built or run**; build it on staging first.

Static review found the image health check invokes `curl`; the image now installs that executable explicitly and checks Laravel's unauthenticated `/up` liveness route. These source-level fixes have not been verified by an image build or runtime smoke test.

- Statelessness: sessions, cache and queue use the database by default; uploaded files live on the `storage` volume. For more than one web container, the files must move to shared or S3-compatible storage and sessions/cache to the database or Redis (decision OD7/D10). Until then do not describe the deployment as horizontally scalable.
- The scheduler also drains the queue every minute (`routes/console.php`); with a dedicated `worker` that is redundant but harmless.
- Release: build image → run `migrate` once → roll `web`, `worker`, `scheduler`. Rollback: redeploy the previous image tag; migrations in this repository are additive, but restore the pre-release backup if a migration must be undone (`operations-runbook.md`).
