# IMS integration

**Date:** 4 November 2026
**Status:** Proposed. **None of the IMS endpoints, events or jobs below exist yet.** IMS has no API today (`discovery.md` 2.5). Building them is IMS work and needs separate authorization. NSP Phase 1 contains no IMS integration code.

## 1. Endpoints IMS would need to add (proposed, does not exist yet)

Mount point depends on the IMS web root (discovery U2). Each route also needs a `ModuleRegistry` entry.

| Method and path | Caller | Auth | Returns |
|---|---|---|---|
| `GET /ims/api/v1/me/applications` | NSP for a signed-in founder | User access token (OIDC), audience `ims-api` | Application summaries: id, programme name, status label, submitted_at, startup_uuid. No scores or comments. |
| `GET /ims/api/v1/me/programs` | NSP | User access token | Current programmes and cohorts: name, cohort, start/end dates, next milestone due date |
| `POST /ims/api/v1/programs/{id}/applications` | IMS UI (Journey B) or NSP hand-off | User access token | Created application id. Body carries `startup_uuid` and the application-time profile snapshot. |
| `GET /ims/api/v1/programs` | NSP public pages | None or client credentials | Open programmes: name, summary, deadline, eligibility summary, apply URL |
| `POST /ims/api/v1/events` | Registry, NSP | HMAC (section 3) | Inbox for incoming events |

Rules:
- TLS only. Least-privilege scopes (`ims.applications.read`, `ims.programs.read`, `ims.applications.write`).
- User-token endpoints return only data for startups the user is a member of, checked against the registry.
- Server-to-server calls use OAuth client credentials when the IdP exists. Until then, HMAC keys per client.

## 2. Event catalogue

| Event | Producer | Consumers | `data` (minimised) |
|---|---|---|---|
| `registry.startup.created.v1` | Registry | IMS, NSP | startup_uuid, display_name, version |
| `registry.startup.updated.v1` | Registry | IMS, NSP | startup_uuid, changed field names, version (not values of H fields) |
| `registry.membership.changed.v1` | Registry | IMS, NSP | startup_uuid, user_subject, role, state |
| `registry.consent.revoked.v1` | Registry | IMS, NSP | startup_uuid, recipient_system, purpose, fields |
| `ims.application.submitted.v1` | IMS | Registry, NSP | startup_uuid, application_id, programme_id, submitted_at |
| `ims.application.accepted.v1` | IMS | NSP | startup_uuid, programme_id, cohort_id, accepted_at |
| `ims.program.enrolled.v1` | IMS | NSP | startup_uuid, programme_id, cohort_id |
| `nsp.assessment.completed.v1` | NSP | Registry (optional) | startup_uuid, framework_version, completed_at (no answers) |
| `nsp.growth.stage.changed.v1` | NSP | IMS (if a use case is approved) | startup_uuid, stage, changed_at |

Consumers fetch full values through the API under their own authorization. Events are notifications, not data copies.

### Envelope

```json
{
  "event_id": "0b6f1c1e-6a8f-4c3e-9c6a-2f1f7d1f0a11",
  "type": "ims.application.accepted.v1",
  "schema_version": 1,
  "occurred_at": "2026-11-04T09:15:00Z",
  "source": "ims",
  "subject_uuid": "startup uuid",
  "correlation_id": "uuid of the user action or job",
  "data": { "programme_id": 12, "cohort_id": 4, "accepted_at": "2026-11-04T09:14:58Z" }
}
```

Spec field `data_minimized` is shown as `data`; it must contain only the fields listed above.

## 3. Signing and replay protection

Headers on every delivery:
- `X-Hive-Event-Id`: the `event_id`.
- `X-Hive-Timestamp`: Unix seconds at send time.
- `X-Hive-Signature`: `v1=` + hex HMAC-SHA256 of `timestamp + "." + raw_body`, with a per-sender secret.
- `X-Hive-Key-Id`: which secret was used, so keys can rotate with overlap.

Receiver checks, in order:
1. Key id is known and active.
2. Timestamp is within 300 seconds of the receiver clock.
3. Signature matches, compared in constant time.
4. `event_id` is not already in the inbox (section 4).
5. JSON schema for `type` and `schema_version` validates.

Failure returns 401 (signature or time) or 422 (schema). Bodies of rejected requests are not logged in full.

## 4. Idempotent inbox

- Table `integration_inbox(event_id PK, source, type, received_at, processed_at, status)`.
- The receiver inserts the row and applies the change in one transaction. A duplicate key means "already processed"; return 200.
- Handlers must also be safe to repeat (upserts keyed by `startup_uuid` and version). An event with an older `version` than stored is ignored.

## 5. Retry, backoff and dead letter

- Outbox row fields: `status` (pending, delivered, dead), `attempts`, `next_attempt_at`, `last_error`.
- Backoff: 1, 2, 5, 15, 30, 60 minutes, then every 6 hours, with random jitter up to 20%.
- After 10 failed attempts (about 2 days) the row becomes `dead` and an admin alert is sent.
- 4xx responses other than 408 and 429 go to `dead` at once; they will not succeed on retry.
- Admins can replay a dead event from an admin screen. Replays keep the same `event_id`.

## 6. Cron-based delivery

Neither IMS nor (possibly) NSP hosting has queue workers (discovery 2.7, U3).
- IMS: a new cPanel cron script `legacy/cron/outbox-deliver.php` every minute.
- NSP: one cron line runs `php artisan schedule:run`; the scheduler runs the outbox command every minute with `withoutOverlapping()`.
- Each run sends at most N events (start with 50) and stops after 50 seconds.
- Rows are claimed with `SELECT ... FOR UPDATE SKIP LOCKED` or a `claimed_at` column if not supported.
- Expected delay is up to about 1 to 2 minutes. User-facing screens must not depend on an event having arrived; they call the API.

## 7. Reconciliation

- A nightly job in each consumer asks the registry for startups changed since the last run (`updated_since`) and compares versions with local caches.
- Differences are fixed from the registry and logged as `reconciled`.
- `GET /integration/sync-status/{uuid}` shows last event, last delivery, failures and dead letters for one startup.
- Weekly report to admins: counts of pending, dead and reconciled items.

## 8. Data NSP must never receive

Unless the owner approves a specific, documented use case, IMS must never send NSP:
- reviewer comments and notes;
- review scores, criteria scores, rankings and shortlisting details;
- AI review outputs (`ai_application_reviews`, `ai_application_review_scores`);
- details of rejected or withdrawn applications (only "not accepted" may appear to the founder's own account, if approved);
- other applicants' data;
- programme-internal documents and staff notes;
- IMS passwords, login codes or session data.

The IMS endpoints must enforce this with an explicit field allow-list, plus a contract test that fails if a blocked field appears.

## 9. Legacy migration dry-run

IMS has no startups table. A startup appears in `applications.startup_name` and `members.company_name` (discovery 2.4).

1. **Extract.** Read-only export from a copy of production: application rows, member rows, submitter user ids and emails. Never run against the live database.
2. **Alias.** Give each source row a candidate key: `startup_aliases(source_system='ims_application', external_id=application_id)` and `('ims_member', member_id)`.
3. **Match (dry run).** Group rows by strong evidence only: same submitting user, same verified website domain, same registration number where present. Normalised names may suggest candidates but never decide a merge.
4. **Report.** Output a CSV and admin screen: proposed groups, evidence, confidence, conflicts. Nothing is written to the registry.
5. **Human review.** Hive staff accept, split or reject each group. Ambiguous groups need confirmation from the startup owner after they sign in.
6. **Apply.** Create registry startups and aliases for approved groups only, with a migration journal for each step.
7. **Rollback.** Take a backup first. Every merge has an unmerge path from the journal.
8. **Reconcile.** Counts by source must match: rows in = aliases created + rows deferred.

Sizing depends on discovery U5 (number of distinct startups).
