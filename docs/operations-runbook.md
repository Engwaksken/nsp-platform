# Operations runbook

For whoever runs Hive NSP in production. Pair it with `deployment.md` (installing and updating).

## 1. What runs on its own
A single cron entry runs the Laravel scheduler every minute: `* * * * * php /path/to/nsp/artisan schedule:run >> /dev/null 2>&1`.

| When | What |
|---|---|
| Every minute | Scheduler heartbeat (used by the health check); queue worker (`queue:work --stop-when-empty`); registry outbox delivery |
| 01:30 daily | `nsp:backup`: encrypted backup, checked after writing, oldest beyond `NSP_BACKUP_KEEP` deleted |
| 02:30 daily | `talent:purge-cvs`: deletes application CV copies past the retention period |
| 03:00 daily | `notifications:prune`: deletes old notifications and delivery-log rows |
| 08:00 daily | `opportunities:send-reminders` |

## 2. Monitoring
- **Admin › System health** (platform admins and auditors) and `php artisan nsp:health` (exits with 1 when something fails) show the same checks: application settings, database, scheduler, queue, failed jobs, deliveries to IMS, email, backups and disk.
- **Uptime monitor:** set `NSP_HEALTH_TOKEN` to a long random value. Point a monitor (for example UptimeRobot or Better Stack) at `GET https://<domain>/health` with the header `Authorization: Bearer <token>`. Alert on any status other than 200. Without a token the endpoint does not exist (404). It returns check names and statuses only.
- `GET /up` is Laravel's basic liveness check: it answers 200 when PHP and the framework boot.

## 3. Backups
**Contents:** one zip per night in `NSP_BACKUP_PATH` (default `storage/app/backups`, outside the web root). Each zip holds:
- the database (`database/database.sql` for MySQL or `database/database.sqlite`);
- private files (data rooms, CVs) under `files/private/`;
- public media under `files/public/`;
- `manifest.json`, with a SHA-256 checksum for every file.

**Encryption:** every entry is encrypted with AES-256 using `NSP_BACKUP_PASSWORD`. Production refuses to write an unencrypted backup.

**Not in the backup, so keep these somewhere safe yourself:**
- The `.env` file, above all **`APP_KEY`**. Two-factor secrets and other encrypted values cannot be read without the same `APP_KEY`. Keep a copy in the organisation's password manager.
- `NSP_BACKUP_PASSWORD` itself. Keep it in the password manager. Without it, the backups cannot be opened.

**Off-server copy (required):** a backup on the same server does not protect against losing the server. Copy the newest zip off the server every day. On a VPS, use `rclone copy` to cloud storage; on cPanel, use a backup destination or a scheduled download. Keep at least 14 daily and 6 monthly copies, as decision D8 allows.

**Checking a backup:** `php artisan nsp:backup --verify-only=latest`, or give a file path. It opens the archive with the password, checks every checksum and opens the database copy.

## 4. Restoring (manual on purpose)
No command overwrites the live system. To restore:

1. Put the site in maintenance mode: `php artisan down`.
2. Copy the chosen zip to a working folder outside the web root. Extract it with a tool that supports AES zip, such as `7z x nsp-backup-YYYYMMDD-HHMMSS.zip` (7-Zip asks for the password).
3. Check the manifest against the files: `php artisan nsp:backup --verify-only=/path/to/zip`.
4. **Database (MySQL):**
   1. Create an empty database, or empty the existing one after taking a fresh dump of it first.
   2. Load the dump: `mysql --defaults-extra-file=<private my.cnf> nsp_db < database/database.sql`.
5. **Database (SQLite):** replace `database/database.sqlite` with the extracted file.
6. **Files:** copy `files/private/*` to `storage/app/private/` and `files/public/*` to `storage/app/public/`. Then run `php artisan storage:link` if needed.
7. Restore `.env` (with the original `APP_KEY`). Run `php artisan migrate --force` (in case the backup predates a release), then `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
8. Check: `php artisan nsp:health`, sign in as an admin, open a startup, download a data-room file, and confirm the latest records you expect are there.
9. Bring the site back: `php artisan up`.

### Restore drill (OPS-703)
Restore the newest backup to a **clean staging server** at least every quarter, and before go-live. Record each drill:

| Date | Backup file | Size | Time to restore (from start to step 8 passing) | Problems | Done by |
|---|---|---|---|---|---|
| | | | | | |

## 5. When a health check fails
| Check | Likely cause | What to do |
|---|---|---|
| Scheduler | Cron entry missing or failing | Check `crontab -l` (or cPanel Cron Jobs). Run `php artisan schedule:run` by hand and read the output. |
| Queue | Scheduler not running, or a job crashing | Fix the scheduler first. Then run `php artisan queue:work --stop-when-empty` and watch the errors. |
| Failed jobs | A job threw an error | `php artisan queue:failed` lists them. Fix the cause, then `php artisan queue:retry all`. |
| Deliveries to connected systems | IMS endpoint down or its secret changed | Admin › Integrations shows the errors. Replay once IMS is back. |
| Email | SMTP settings wrong, or the provider is rejecting mail | Check `MAIL_*` in `.env`, then send a test from Admin › Settings. Admin › Announcements and email log shows each error. |
| Backups | Disk full, wrong password or mysqldump missing | Run `php artisan nsp:backup` by hand and read the error. Check `NSP_MYSQLDUMP` points to mysqldump. |
| Disk space | Logs, backups or uploads growing | Lower `NSP_BACKUP_KEEP`, rotate `storage/logs`, and move old backups off the server. |
| Application settings | `APP_DEBUG=true` or http in production | Fix `.env`, then run `php artisan config:cache`. |

## 6. Incidents
1. **Contain:** if data might be exposed, put the site in maintenance mode, or switch the affected module off under Admin › Features.
2. **Assess:** the audit log (Admin › Audit log) records sign-ins, exports, downloads, data-room access and staff actions. `storage/logs/laravel.log` has errors.
3. **Rotate secrets** if they may have leaked: database password, `MAIL_PASSWORD`, API client secrets (`php artisan nsp:api-client`), `ANTHROPIC_API_KEY`, `NSP_HEALTH_TOKEN`. Change `APP_KEY` only with a plan: it signs people out and makes values encrypted with it unreadable.
4. **Personal data breach:** inform the data protection lead at once. Uganda's Data Protection and Privacy Act requires notifying the Personal Data Protection Office. Keep a written timeline.
5. **Afterwards:** write down what happened, the cause and the fix, and update this runbook.

## 7. Routine checks
- **Weekly:** Admin › System health is all OK. There are no failed emails or failed jobs left over.
- **Monthly:** run `composer audit` on the server's code (CI also runs it on every push). Review the admin users and their roles under Admin › Users.
- **Quarterly:** restore drill (section 4). Review who holds the backup password and the `.env` copy.
