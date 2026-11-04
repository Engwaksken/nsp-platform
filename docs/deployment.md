# Deployment (draft: not yet used)

Nothing has been deployed. This is the intended procedure for staging and then production. Production domains are decision D9.

## Hosting requirements
- PHP 8.2+ with these extensions: ctype, curl, dom, fileinfo, filter, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip.
- MySQL 8 (or MariaDB 10.6+) with utf8mb4.
- HTTPS (Let's Encrypt from cPanel AutoSSL is enough).
- No Node.js is needed. CSS and JS are plain files in `public/`.
- Cron, every minute.

## First install (cPanel)
1. Create the database and user in cPanel. **Never reuse the IMS database or its user.**
2. Upload the code, without `vendor/`, `.env` or `database/database.sqlite`, to a folder **outside** `public_html`, for example `~/nsp`.
3. Point the subdomain's document root to `~/nsp/public`. If cPanel can't, symlink `public_html/nsp` → `~/nsp/public`.
4. In `~/nsp`:
   ```
   composer install --no-dev --optimize-autoloader
   cp .env.example .env      # then edit: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, MAIL_*
   php artisan key:generate
   php artisan migrate --force
   php artisan db:seed --force          # roles, permissions, draft placeholder pages
   php artisan storage:link
   php artisan nsp:create-admin you@hive.example   # asks for the password; never pass it on the command line
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
2. Run `php artisan down --retry=60`.
3. Upload the new code, then run `composer install --no-dev --optimize-autoloader` and `php artisan migrate --force`.
4. Run `php artisan db:seed --class=RolesAndPermissionsSeeder --force` when a release adds permissions (it is safe to repeat; Phase 5b added `verify-investors`, 5c added `manage-experts`, 5d added `manage-talent`, 5e added `moderate-marketplace`, 6a added `manage-communications`, 6b added `view-insights`, 7a added `view-system-health`).
5. Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`, then `php artisan up`.

Data-room files (`storage/app/private/data-room`) and candidate CVs (`storage/app/private/talent`) live (the private `local` disk). Include that folder in backups; never link it into `public`.

**Rollback:** restore the previous code folder, restore the database dump if a migration ran, then clear and rebuild the caches.

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
