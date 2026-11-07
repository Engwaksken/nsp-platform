# Current state audit (Phase 0 reconciliation)

**Date:** 7 November 2026 (updated after owner-alignment code was present)
**Auditor:** OpenCode PM; workspace inspection and local automated test run.
**Repository:** `D:\projects\nsp-platform`, branch `owner-alignment`. Working tree contains pre-existing uncommitted owner-alignment work; it was preserved.
**Directive:** `NSP_Owner_Requirements_Alignment_OpenCode_PM_Implementation.md` (repo root).

## 1. How this was checked
- Read `README.md`, `composer.json`, `config/nsp.php`, `routes/web.php`, `routes/api.php`, all migrations (18 files), seeders, the Growth/VGI, Funding, Experts, Ecosystem, AI, Analytics and Registry code, the layouts and workspace views, `docs/phase-status.md`, `docs/decisions.md`, `docs/backlog.md`.
- Ran the full test suite: `php artisan test` → **224 passed (1537 assertions), 82.8 s**, PHP 8.2.12, SQLite in memory. (Parallel mode is not installed: ParaTest is missing.)
- No database server, staging or production system was touched. Nothing is deployed (per `phase-status.md`).

## 2. Stack facts
| Item | Fact | Evidence |
|---|---|---|
| Framework | Laravel 12.71 on PHP 8.2 (directive suggests Laravel 13 only if compatible; decision D11 still pending) | `composer.json`, `docs/decisions.md` M1 |
| Front end | Blade, one CSS file and one JS file, no Node build; CSP forbids inline scripts | `public/css/nsp.css`, `public/js/nsp.js` |
| Auth | Local login, OIDC client (code + PKCE) behind flag, TOTP MFA enforced for admin roles, password reset | `OidcLoginTest`, `MfaTest`, `PasswordResetTest` |
| RBAC | spatie/laravel-permission, 11 platform roles | `RolesAndPermissionsSeeder` |
| Startup identity | Canonical `startups` (UUID) with memberships, invitations, consent, duplicate review/merge, outbox events — registry hosted inside NSP (decision M8) | `tests/Feature/Registry` |
| IMS integration | Connect flow, Registry API v1, signed inbound/outbound events, retries, My Applications/My Programs (flag off by default) | `tests/Feature/Integration` |
| Containers | **None**: no Dockerfile or manifests. Deployment doc targets shared hosting/VPS | `docs/deployment.md` |
| CI | GitHub Actions workflow (Pint, tests on 8.2/8.3, composer audit) — no remote yet | `.github/workflows/tests.yml` |

## 3. What already exists, by owner module

| Owner module | What the code already does | Gaps against the directive |
|---|---|---|
| M01 Identity & organizations | Users, roles, MFA for admins, OIDC client, password reset, startup memberships (multi-startup), invitations, consent, audit and login logs, admin user management | **No organizations** (universities, ESOs, investors, partners, corporates) as tenants; no organization memberships; no organization ↔ startup relationship/access grant; no tenant switcher for institutions |
| M02 Venture Builder (6 × 24) | Nothing. Growth plans/actions exist (from VGI gaps) but no structured builder | **Missing entirely** |
| M03 VGI (10 dimensions) | Configurable JSON rubric, draft/approve/retire, four-eyes approval, deterministic scoring + hash, history, gap → growth plan, staff override with reason, printable report. Starter draft has **6 areas / 18 questions** | Not the ten owner dimensions; no methodology status (provisional vs owner-approved); no evidence adequacy or insufficient-information state; no investment/commercialization readiness; no per-dimension trend; override kept as single columns (no append-only log); no Builder deep links; no API |
| M04 Venture Intelligence | Rules-first opportunity recommender with reasons and "not relevant" feedback; VGI gap suggestions; optional Anthropic writing assistant (locked until legal review, D13) | No unified guidance engine across VGI + Builder + services; no accept/dismiss/feedback on guidance; no versioned generated documents; no persistent authorized venture context; no prompt/template registry, evaluation fixtures or usage logs beyond the assistant |
| M05 Challenge Exchange | Nothing (opportunities are a separate module) | **Missing entirely** |
| M06 Knowledge Hub | News/posts CMS and media library only | **Missing**: resource repository, taxonomy, visibility/licence, review/version, bookmarks, downloads, search abstraction |
| M07 Expert Marketplace | Expert verification, directory with reasons, consultation/mentoring requests, engagements, sessions, actions, private notes, completion + rating | Missing: expert categories beyond mentor/consultant, credential records, service packages, availability/booking with double-booking protection and time zones, private messaging, contract acknowledgement, payment adapter/ledger/webhooks |
| M08 Capital Exchange | Investor verification, funding request, `investor_listing` consent, explainable matching, introductions, private pipeline stage + notes, private data room with expiring/revocable grants and download logs | Missing: due-diligence checklists and document requests, granular per-investor scopes (VGI/builder/traction), investment-readiness report, watchlist, configurable pipeline with event history and funnel, match feedback |
| M09 Dashboards | Founder workspace + per-startup overview and export; investor dashboard; expert dashboard; admin KPIs with exports and definitions (`kpi-definitions.md`), system health | No institution (university/ESO/partner/corporate) portfolio dashboard; founder dashboard does not combine VGI trend, Builder progress and guidance; no investor funnel; admin lacks challenge/knowledge/builder/payment queues |
| M10 Retained services | Opportunities/events, talent/jobs, marketplace/showcase, communications, CMS, accessibility, security | Retain as is |

## 4. Risks observed
1. **Methodology**: the owner has not supplied 24 step names, VGI questions, weights or thresholds. All seeded content must stay provisional (directive §11 decisions 1–2).
2. **No containers / cloud manifests**: directive §4 asks for container images; current hosting decision (D10) is open.
3. **Shared files**: routes, config and seeders are single files; parallel development needs per-module route files and config files (done in commit `b363900`).
4. **Laravel 13**: not adopted; upgrade remains decision D11.

## 5. Owner-alignment implementation now present in working tree
- Organization, builder, VGI owner dimensions, challenge, knowledge, expert marketplace and capital exchange files/migrations/config/routes are present in the working tree. This does not establish end-to-end completeness; see `gap-matrix.md`.
- Feature flags and routes/modules navigation hooks are present.
- Dockerfile, compose and deployment configuration are present but not built or deployed.
- VGI starter seeder was aligned to the provisional owner-dimension draft; its legacy Growth tests now expose evidence/schema expectation mismatches (see `test-matrix.md`).

## 6. Verification update

- Current `php artisan test`: 218 passed, 6 failed before VGI seeder alignment.
- After seeder alignment, targeted `php artisan test --filter=GrowthTest`: 4 passed, 5 failed due to legacy test assumptions (dimension scores/bands) and new required evidence fields. The test matrix records this regression explicitly.
- No changes were made to IMS production data; no staging/production environment was accessed.
- Suite re-run after scaffolding: 224 passed.
