# NSP ⇄ IMS integration contract, v1

**Status:** implemented in Phase 3 (see `phase-status.md`). Both sides are behind switches that are off by default:
- NSP: the `ims_integration` feature flag.
- IMS: `NSP_INTEGRATION_ENABLED=false`.

There is no shared identity provider yet (decision D1 is deferred). Users authorize sharing in NSP with an authorization-code flow, and systems authenticate to each other with client credentials. When an IdP is chosen, the browser part can move to OIDC; the API and events stay the same.

## 1. Clients and secrets
NSP admins create one **API client** per connected system (`php artisan nsp:api-client`). Each client has:

| Field | Purpose |
|---|---|
| `client_id` | Public identifier, for example `ims-live` |
| `client_secret` | For server-to-server API calls. Shown once and stored hashed in NSP. |
| `webhook_secret` | Signs events in both directions. Shown once and stored encrypted (NSP needs it to sign). |
| `redirect_uris` | Exact URLs allowed for the connect flow. No wildcards. |
| `webhook_url` | Where NSP delivers events. Must be HTTPS outside local development. |
| `recipient_system` | `ims`. Decides which consent purpose applies. |
| `scopes` | `registry.read`, `registry.link`, `events.write` |

**API authentication:** send `Authorization: Bearer {client_id}.{client_secret}` over HTTPS. A wrong client, a wrong secret or an inactive client gets 401. A missing scope gets 403.

## 2. Connect flow (founder authorizes IMS to use the startup profile)
1. IMS creates a random `state` (at least 32 characters), keeps it in the user's IMS session, and redirects the browser to
   `GET {NSP}/connect/{client_id}?redirect_uri={exact registered URI}&state={state}`.
2. In NSP, the user signs in and picks one of their startups (they must be an active member). They see what will be shared: the `ims_prefill` purpose and its fields.
   - If sharing is already on, they confirm.
   - If it is off, an owner or founder turns it on here; other roles are told to ask an owner or founder.
3. NSP redirects to `{redirect_uri}?code={code}&state={state}`. The code is single-use, valid for 5 minutes, and tied to the client and redirect URI. If the user cancels, NSP redirects to `{redirect_uri}?error=access_denied&state={state}`.
4. IMS checks that `state` matches the session, then calls the token endpoint server-to-server:

```
POST {NSP}/api/v1/connect/token
Authorization: Bearer {client_id}.{client_secret}
Content-Type: application/json

{"code": "...", "redirect_uri": "..."}
```

`200` response:

```json
{
  "startup_uuid": "01a1a5a1-...",
  "version": 3,
  "fields": {
    "legal_name": "...", "display_name": "...", "registration_number": "...", "country": "UG",
    "year_founded": 2023, "description": "...", "website": "...", "sector": "...",
    "headquarters_location": "...", "team_size": 6
  },
  "consent": {"purpose": "ims_prefill", "policy_version": "2026-11-05", "granted_at": "2026-11-05T10:00:00+03:00"},
  "user": {"name": "...", "email": "..."}
}
```

`fields` contains only the fields in the consent's scope; financial fields are never included. An invalid, expired or already-used code, or a mismatched redirect URI, returns `400 {"error": "invalid_grant"}`.

IMS stores the returned fields as an **application-time snapshot** with the application. Later changes in NSP never change a submitted application (spec §7, conflict resolution).

## 3. Registry API (server to server)

| Method and path | Scope | Result |
|---|---|---|
| `GET /api/v1/startups/{uuid}` | `registry.read` | `200` with the same `startup_uuid`, `version` and `fields` as above. `403 {"error":"consent_required"}` if the startup has not consented for this client's system. `404` if unknown. A merged startup returns `301` with `Location` set to the surviving startup. |
| `POST /api/v1/startup-links` | `registry.link` | Body `{"startup_uuid", "external_id"}`. Links an ID in the client's system (for example an IMS application ID) to the startup. Calling it again for the same link is fine (`200`). Returns `409` if the ID is already linked to a different startup. Consent is required. |
| `GET /api/v1/integration/sync-status/{uuid}` | `registry.read` | Events delivered or pending for this startup and client, plus the last error, so failures are visible. |

All `/api/v1` responses are JSON and rate limited to 120 requests a minute per client.

## 4. Events (both directions)
**Envelope** (JSON body):

```json
{
  "event_id": "uuid",
  "type": "ims.application.submitted.v1",
  "schema_version": 1,
  "occurred_at": "2026-11-05T10:00:00+03:00",
  "source": "ims",
  "subject_uuid": "startup uuid",
  "correlation_id": null,
  "data": {}
}
```

**Signature headers:**
- `X-Hive-Timestamp: {unix seconds}`
- `X-Hive-Signature: sha256={hex HMAC-SHA256 of "{timestamp}.{raw body}" using the client's webhook_secret}`

The receiver:
- recomputes the signature over the **raw** body and compares it in constant time;
- rejects requests whose timestamp is more than 300 seconds old or ahead (replay protection);
- records each `event_id` once (idempotent inbox). A repeat delivery is acknowledged and changes nothing.

**Response codes:**

| Code | Meaning |
|---|---|
| `202` | Processed |
| `200` | Duplicate, already processed |
| `400` | Malformed, or an unknown subject. The receiver also records it. |
| `401` | Bad signature or timestamp |
| `5xx` | Temporary failure; the sender retries |

### 4.1 IMS → NSP: `POST {NSP}/api/v1/events` (scope `events.write`)

| Type | `data` (whitelist; anything else is dropped) |
|---|---|
| `ims.application.submitted.v1` | `application_id`, `program_title`, `cohort_name`, `submitted_at` |
| `ims.application.status_changed.v1` | `application_id`, `status` (`Submitted`, `Under Review`, `Shortlisted`, `Accepted`, `Selected`, `Rejected`, `Withdrawn`), `program_title`, `cohort_name`, `changed_at` |
| `ims.program.enrolled.v1` | `application_id`, `program_title`, `cohort_name`, `start_date`, `end_date` |

`subject_uuid` is the startup UUID IMS received in the connect flow. NSP links `application_id` to the startup automatically.

**Never sent:** scores, reviewer comments, application answers, eligibility notes or attachments (spec §5 Journey A, §12).

### 4.2 NSP → IMS: `POST {webhook_url}`

| Type | IMS must |
|---|---|
| `registry.consent.revoked.v1` (`data.purpose`, `data.recipient_system`) | If the purpose is `ims_prefill`: stop using NSP data for future prefill, and mark linked applications as consent revoked. Snapshots already submitted stay, as required by its records policy. |
| `registry.startup.merged.v1` (`data.source_uuid`, `data.target_uuid`) | Replace `source_uuid` with `target_uuid` on its records |
| `registry.startup.unmerged.v1` | Optional: log it. Staff decide manually. |
| Any other type | Acknowledge with 2xx and ignore |

NSP only delivers events to a client for startups that have an alias in that client's system (they connected or applied through it), and never includes private values.

If IMS has no applications for the subject (for example the founder connected but never applied), it answers `202` and records the event. This is not an error.

### 4.3 Delivery and retries (both sides)
- Events are written to an **outbox** in the same database transaction as the change.
- A cron job (every minute) sends pending events.
- After a non-2xx response or a timeout (10 seconds), the event is retried after 1, 5, 15, 60 and 240 minutes, then every 12 hours.
- After 8 failed attempts it is marked **dead**. It shows in the admin area with its last error and can be replayed by hand.

## 5. Versioning
Types end in `.v1`. A breaking change adds a `.v2` type, and both versions are sent during migration. New optional fields may be added to `data` without a version change, so receivers must ignore fields they don't know.
