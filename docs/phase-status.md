# Phase status report

**Date:** 4 November 2026
**Status words used here:** *Implemented* means the code exists in this repository. *Tested* means automated tests pass locally (PHP 8.2.12, SQLite in memory). *Staged* and *Deployed* mean released to staging or production; **nothing is staged or deployed yet.**

## Summary

| Phase | Status | Gate |
|---|---|---|
| 0: Discovery and architecture | Documents written. Owner decisions D1 to D11 are pending (`decisions.md`). | Not passed: needs owner approval of the architecture |
| 1: Foundation and CMS | Implemented and tested locally | CMS and admin permissions pass tests. Not staged. |
| 2: Identity and registry | **Registry implemented and tested locally** (inside NSP, decision M8). Shared login (OIDC) deferred by owner (D1). | Partly: "NSP-first" journey works without duplicates; the IMS-first journey needs IMS changes (Phase 3) and an IdP. |
| 3: IMS integration | **NSP side implemented and tested locally.** IMS side implemented in `ims-site`, switched off by default (see `integration-contract-v1.md`). Not staged. | Not passed: needs both sides on staging and an end-to-end run with real IMS data |
| 4: Growth and VGI | **Implemented and tested locally.** The starter rubric is seeded as an unapproved DRAFT; founders see nothing until Hive approves a version. | Reproducible scoring and authorized access pass tests. Needs Hive to review and approve the rubric (decision D12). |
| 5: Ecosystem services | **5a Opportunities and events implemented and tested locally.** Still to build: 5b funding and investors (data room), 5c experts and mentors, 5d talent, 5e marketplace. | Not passed: needs all five modules and role-based UAT |
| 6 to 7 | Not started. Planned in `backlog.md`. | — |

## Phase 0: completed this sprint
- `discovery.md`: read-only audit of Hive IMS. Confirmed: no IdP, no API, no startups table, shared hosting with cron only.
- `architecture.md`, `data-ownership.md`, `erd.md`, `oidc-sso.md`, `ims-integration.md`, `api/openapi.yaml` (a draft contract that is not implemented), `privacy-security.md`, `decisions.md`, `backlog.md`.

## Phase 1: implemented

| Area | What exists | Evidence |
|---|---|---|
| Foundation | Laravel 12.71, PHP 8.2, no Node build step (one CSS file and one JS file in `public/`) | `composer.json` |
| RBAC | 11 platform roles and 12 permissions (spatie/laravel-permission). Super admin passes every check. Only a super admin can grant privileged roles. | `RolesAndPermissionsSeeder`, `AdminPermissionsTest` |
| Local login | Email and password login. 5 tries, then a lockout. Same message for a wrong password or an unknown email. Inactive accounts are blocked. Sign-ins are audited with hashed identifiers. | `LocalLoginTest` |
| OIDC client | Authorization code + PKCE S256, state, nonce, JWKS signature check, iss/aud/azp/exp checks, asymmetric algorithms only, key-rotation retry, RP-initiated logout. Users are matched by (issuer, subject). No email linking unless enabled. Behind the `oidc` flag. | `OidcLoginTest` (fake IdP) |
| Public CMS | Pages built from 8 section types, posts/news with categories, FAQs, testimonials (shown only with recorded consent), partners, menus, sitemap, robots, canonical and Open Graph tags | `PublicSiteTest` |
| Publishing workflow | Draft, published and archived states, plus scheduling through a future publish date. Preview for editors. Content editors save drafts only; publishing needs `publish-content`. | `AdminPermissionsTest` |
| Revisions | A snapshot on every save. Restoring copies the content back as a draft. | `PageRevisionsTest` |
| Media library | Images and PDF up to 5 MB. The file type is checked from the file's contents. Files get random names. | `RedirectsAndMediaTest` |
| SEO redirects | 301 or 302 for paths that would 404. Targets must be site paths or http(s) links. | `RedirectsAndMediaTest` |
| Contact form | Topic routing, consent with a versioned wording, a honeypot, a minimum fill time, and rate limits (3 per minute, 20 per day per IP). IPs are stored as keyed hashes. Mail is queued. | `ContactFormTest` |
| Branding | Site name, logo, favicon, three colours with a WCAG contrast check, Poppins, Outfit or system font, contact details, social links | `AdminPermissionsTest`, `SupportTest` |
| Feature flags | Database overrides of config defaults. Modules that aren't built can't be switched on. Admins can't turn off every sign-in method. | `FeatureFlagsTest` |
| Audit | `audit_logs` (changes, contact-message reads) and `login_audits` | Several tests |
| Security headers | CSP with no inline scripts, frame-ancestors none, nosniff, Referrer-Policy, Permissions-Policy, HSTS on HTTPS | `PublicSiteTest` |
| Workspace | A founder landing page with the unified navigation. Each service shows "Not available yet" until its flag is on. It never invents startup data. | `FeatureFlagsTest` |
| Registry adapter | `StartupRegistry` interface plus `DisabledStartupRegistry` | — |

## Follow-up fixes (same day)
| Gap | Fix | Evidence |
|---|---|---|
| No MFA for privileged users | Every admin role must use an authenticator app (TOTP, RFC 6238). A password alone no longer signs an admin in. Includes 8 one-time recovery codes (stored hashed), a 5-try limit, replay protection, an encrypted secret, and a super-admin reset for lost phones. SSO logins skip it only when the IdP reports MFA in `amr`. | `MfaTest`, `TotpTest` (RFC 6238 vectors) |
| No password reset | Email reset link for local accounts. Generic answer (doesn't reveal accounts), 60-minute single-use token, 12-character minimum, all sessions signed out, rate limited. SSO-only and inactive accounts get no link. | `PasswordResetTest` |
| No accessibility audit | axe-core WCAG 2.1 A/AA audit of 25 pages (public, auth, workspace, all admin screens) at 1280 px and 360 px in headless Chrome (`tests/a11y`). The first run found 23 issues: low contrast in the footer, a header button with inherited dark text, tables that couldn't be scrolled by keyboard, and sideways scrolling at 360 px. All fixed; the re-run found **0 violations**, and the first Tab press reaches the skip link. | `tests/a11y/audit.js` |
| No CI | `.github/workflows/tests.yml`: Pint, tests on PHP 8.2 and 8.3, `composer audit`. It runs once a GitHub remote exists. | — |

An automated audit catches only part of WCAG. A manual screen-reader check (NVDA or TalkBack) is still needed before claiming AA.

## Phase 2: startup registry (implemented 2026-11-05)

| Area | What exists | Evidence |
|---|---|---|
| Canonical startup | One record per company with a stable UUID, core fields, a separate profile, version number, status (active/archived/merged) | `StartupRegistryTest` |
| Privacy | Revenue, funding, traction and phone encrypted at rest. Per-group visibility: editors only / whole team / team and Hive staff. Financials default to editors only. Staff views are audited. | `StartupRegistryTest`, `DuplicateReviewTest` |
| Duplicates at sign-up | Same registration number or website blocks a second record and offers "Ask to join". Same name only warns; if the user confirms it is a different company, the pair is queued for staff review. | `StartupRegistryTest` |
| Team | Roles owner, founder, admin, team member, viewer. Email invitations (single use, 7 days, only for the invited address), join requests with approval, role changes, leaving. A startup always keeps an owner. | `StartupRegistryTest` |
| Tenant isolation | Non-members get 404 on every startup page and action, so a changed UUID reveals nothing. A startup selector keeps the current startup per session and is re-checked on each request. | `StartupRegistryTest::test_founder_of_two_startups_cannot_open_a_third_by_changing_the_url` |
| Concurrent edits | Saves check the version; a stale form is refused, never overwrites | `StartupRegistryTest` |
| Change history | Every change recorded with who, when and version. Private values are never stored, only "[changed]". | `StartupRegistryTest` |
| Consent | Three purposes (IMS prefill, NSP directory, anonymised reports) with versioned wording. Grant/revoke by owners and founders; revocation is published as an event. | `StartupRegistryTest` |
| Duplicate review and merge | Scan by registration, website and name (scored 90/70/30, re-scans don't inflate). Reviewer must write evidence. Merge moves team, founders and links, keeps an old-ID redirect, never moves consents. Every merge can be undone exactly. | `DuplicateReviewTest` |
| Outbox | `registry.startup.created/updated/merged/unmerged`, `registry.membership.changed`, `registry.consent.granted/revoked` written in the same transaction, without private values. Delivery is Phase 3. | Tests above |
| Aliases | Staff can link an ID from another system (for example an IMS application) to a startup | Admin screen |
| Accessibility | axe WCAG 2.1 A/AA audit of all 12 registry pages at 1280 and 360 px: 0 violations | `tests/a11y` |

Not yet: the Registry API for IMS (Phase 3), the IMS-first journey, importing existing IMS startups (Phase 3 dry-run), shared login (D1).

## Phase 3: IMS integration (implemented 2026-11-05, NSP side)

| Area | What exists | Evidence |
|---|---|---|
| Contract | `docs/integration-contract-v1.md`: clients, connect flow, Registry API, events, signatures, retries | — |
| Connected systems | API clients with hashed secret, encrypted webhook secret, exact redirect URIs and scopes; created and rotated with `php artisan nsp:api-client` (secrets shown once) | `CommandsTest` |
| Connect flow (journey that starts in NSP) | The founder picks a startup and approves sharing; owners and founders can turn sharing on there. Single-use 5-minute codes bound to client and redirect URI; any failed try uses the code up. Only consented core fields are returned, never financials. The page's CSP allows the redirect back to the registered IMS address only. | `ConnectFlowTest` |
| Registry API v1 | `connect/token`, `startups/{uuid}` (consent required; merged → 301), `startup-links` (idempotent, 409 on conflict), `integration/sync-status/{uuid}`; per-client rate limit; JSON errors | `RegistryApiTest` |
| Incoming events | HMAC signature over timestamp and raw body, 5-minute window, idempotent inbox, type and field whitelists (scores, comments and answers are dropped), merged-startup redirection, cross-startup link protection; rejected events recorded | `InboundEventsTest` |
| Applications and programs | IMS events build "My applications" and "My programs" for the startup's team only | `InboundEventsTest` |
| Outgoing events | Per-client deliveries only for startups linked to that system; signed; retried after 1, 5, 15, 60 and 240 minutes, then every 12 hours; dead after 8 tries; admin page with "Send again" | `OutboxDeliveryTest` |
| End-to-end (local) | Both apps run together on 2026-11-04: NSP (`artisan serve`) and IMS (`php -S`, scratch MariaDB), each with its own secrets. Checked: IMS "Prefill" → NSP connect → approve (sharing turned on) → IMS callback exchanged the code once; replaying it was refused. IMS kept only consented fields (no revenue or traction). Submission stored the snapshot and queued `ims.application.submitted.v1`; IMS cron delivered it, then `status_changed` (Accepted), and NSP built the application record (program, cohort, Accepted) and linked the IMS ID. Revoking sharing in NSP delivered signed `consent.revoked`; IMS marked the application revoked; redelivery was treated as a duplicate. Not covered: the full IMS application form with real opportunity data (needs staging). | Manual run, scratch databases dropped afterwards |
| Legacy import (Journey C) | `php artisan registry:import-ims file.csv` dry run; `--apply` links strong matches only, and lists name-only matches for review | `CommandsTest` |

## Phase 4: growth assessments and growth plans (implemented 2026-11-06)

| Area | What exists | Evidence |
|---|---|---|
| Configurable rubric | Dimensions with relative weights, weighted questions, 2–7 options scored 0–100 with improvement tips, evidence none/optional/required, stage bands covering 0–100. Edited as JSON with plain-language validation and a readable preview. | `ScorerTest`, `GrowthTest` |
| Versions and approval | Draft → approved → retired. Four-eyes: the last editor cannot approve (super admins excepted), and an approval note is required. Approved versions are frozen; a new approval retires the old one for new assessments only. | `GrowthTest` |
| Starter rubric | 6 areas, 18 questions, 5 stages (`database/data/vgi_starter_draft.php`): a **proposal** seeded as an unapproved draft | `ScorerTest::test_the_seeded_starter_rubric_is_valid` |
| Deterministic scoring | Weighted averages with fixed rounding; a calculation hash over the rubric and answers. Staff can re-verify any result against its stored version. | `ScorerTest`, `GrowthTest` |
| Founder journey | Start, save and continue, submit (all answers and required evidence); results by area with "self-assessment, not a verified rating"; history; printable report (save as PDF). Owners, founders and admins fill it in; the whole team can see it; non-members get 404. | `GrowthTest` |
| Gap analysis and plan | Answers scoring below 50 become suggestions, weakest first, each explained ("Because you answered …"). One click adds one to the growth plan; actions have owners (team only), due dates, status and progress notes. | `GrowthTest` |
| Human override | Staff adjust the score or stage only with a reason; the calculated result is kept; founders see both, with the reason; audited | `GrowthTest` |
| Events | `nsp.assessment.completed.v1`, `nsp.growth.stage.changed.v1` in the outbox (not sent to IMS) | `GrowthTest` |
| Accessibility | axe audit of 7 growth pages at 1280 and 360 px: 0 violations | `tests/a11y` |

Not done: benchmarks against other startups (spec: only where data justifies it), and AI suggestions (Phase 6).

## Phase 5a: opportunities and events (implemented 2026-11-07)

| Area | What exists | Evidence |
|---|---|---|
| Directory | Public list of open, published grants, accelerators, competitions, events, tenders, partnerships and investment calls. Search, type, sector, online and "closing in 14 days" filters. Detail page with the organiser's link. | `OpportunitiesTest` |
| Eligibility | Rules for sectors, countries, growth stages, registered companies only and maximum company age. On each opportunity, a founder sees ✓, ✗ or "unknown, add it to your profile" for their current startup, labelled as guidance only. | `OpportunitiesTest` |
| Recommendations v1 | Rules first: no failed rule, ranked by matched rules then deadline, with the reasons shown ("Matches sector · Closes in 3 days"). "Not relevant" hides an item for that person. | `OpportunitiesTest` |
| Save and remind | Bookmarks; one email reminder 3 days before the deadline (daily 08:00) | `OpportunitiesTest` |
| Registration and interest | For opportunities that allow it: register for yourself or for a startup you belong to; capacity limits; withdraw. The organiser sees name, email and startup only. | `OpportunitiesTest` |
| Submissions and moderation | Any signed-in user can share an opportunity; it stays hidden until staff publish it; a rejection needs a reason the submitter sees. Users cannot switch on registration. | `OpportunitiesTest` |
| Staff | Create and edit; open, review, draft, closed, rejected and archived lists; registrations with status; CSV export (audited, protected against spreadsheet formulas) | `OpportunitiesTest` |
| Accessibility | axe audit of 8 opportunity pages at 1280 and 360 px: 0 violations | `tests/a11y` |

## Test results (local)
- `php artisan test`: **158 passed, 855 assertions** (end of Phase 4: 149 and 796).
- `vendor/bin/pint --test`: passed.
- Manual smoke test with `php artisan serve`:
  - every public route returned the expected status;
  - a local admin signed in;
  - all 9 admin screens loaded;
  - a page was published through the form and its Markdown rendered.
- The temporary smoke-test admin was deleted afterwards.

## Not done in Phase 1 (honest gaps)
- ~~MFA for privileged users~~ and ~~password reset~~: fixed, see "Follow-up fixes".
- **Multilingual content:** pages have a `locale` column, but there is no translation UI.
- **Contact-form tickets:** messages are a simple inbox with statuses, not a full ticket system (Phase 6, support tickets).
- **Analytics:** no tracking is installed. It needs a privacy decision.
- **Accessibility:** the automated axe audit passes. A manual screen-reader audit hasn't been done, so WCAG 2.1 AA isn't claimed.
- **CI:** the workflow file exists, but it can't run until a remote repository exists (M7).
- **Screenshots:** none captured in this sprint.

## Risks
1. Two-factor sign-in and password reset depend on working email (SMTP) settings in production.
2. IMS integration needs IMS code changes and an IdP. Neither is authorized yet.
3. Shared hosting with no queue worker means mail is sent from cron (`deployment.md`).
4. Spec deviations: Laravel 12 instead of 13, and a local login fallback where the spec has OIDC only. Both are recorded in `decisions.md`.

## Decisions needed (owner)
D1 IdP, D2 registry hosting, D4 consent wording, D9 domains, D10 hosting. D1 and D2 block Phase 2.

## Next tasks
1. Owner review of the architecture and decisions (Phase 0 gate).
2. Create a remote repository and CI that runs tests and Pint on every push.
3. Staging deployment (see `deployment.md`), then run an axe accessibility audit and capture screenshots.
4. Phase 2 backlog items once D1 and D2 are decided.

## Release notes (for users), Phase 1
- New NSP website with editable pages, news, FAQs, partners and testimonials.
- Staff admin area with roles: drafts and publishing, scheduled publishing, page history, media library, menus, redirects and branding.
- A contact form that sends each topic to the right team and asks for consent.
- Sign-in with email and password. Hive single sign-on is ready to switch on when the Hive identity service exists.
