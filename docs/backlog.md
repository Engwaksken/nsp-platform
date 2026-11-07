# Backlog (legacy phases plus owner-alignment work)

**Date:** 7 November 2026 (owner-alignment update)
**Status:** Mixed: legacy work below records prior plan; owner-alignment code is already in progress on `owner-alignment`. See `docs/owner-alignment/gap-matrix.md`; the working tree must be preserved. Estimates: S = up to 2 days, M = 3-5 days, L = 1-2 weeks, for one developer. Owner roles: backend, frontend, security, QA, DevOps, data, docs, PM.

## Owner-alignment implementation backlog (prioritized, additive)

| ID | Priority | Work item | Status / dependencies | Acceptance criteria |
|---|---|---|---|---|
| OA-00 | P0 | Complete evidence-based audit, current state, gap matrix, decisions and test matrix | In progress; initial audit recorded in `docs/owner-alignment/` | Every owner module has status, code evidence, tests, risks and next action; no production claims |
| VGI-01 | P0 | Repair VGI owner-dimension integration and legacy test fixtures/approval flow | Done locally; exact ten dimensions retained | Focused Growth tests and full suite pass; provisional methodology clearly labelled; historical rubric snapshots remain immutable |
| VGI-02 | P1 | Add explicit insufficient-information, dimension-trend and readiness-not-configured edge tests | Done locally; scoring tests plus same-rubric dashboard trend | Missing evidence never creates invented certainty; readiness remains `not_configured` without approved rules; cross-rubric versions are labeled not comparable |
| ORG-01 | P0 | Complete organization memberships, scoped startup relationships/grants, revocation and role dashboard | Partially verified locally; feature tests cover invite identity/expiry/decline/reinvite, tenant roles, scoped consent and revocation | manual UX/UAT and staging verification remain; authorized institution portfolio only; revoked access immediately denied |
| VB-01 | P0 | Verify metadata-driven curriculum invariant and provisional 6-module/24-step content | Done locally; scaffold plus invariant tests | Published curriculum has exactly six modules and 24 active ordered steps; provisional status explicitly disclosed; wrong active count is rejected |
| VB-02 | P0 | Builder enrolment, step response/draft autosave, dependencies, progress and resume | Done locally for core journey | Enrollment pins published version; drafts resume; prerequisites lock/unlock; required fields/checklist gate submission; stale version is refused |
| VB-03 | P0 | Evidence, checklists, validator/reviewer permissions, feedback and state history | Core workflow done locally; advanced controls remain | Required evidence and checklist gate submission; private upload/link; reviewer permission; changes-requested/revise/validate; append-only revisions/events |
| VB-04 | P1 | Editable/versioned builder artifacts and exports; VGI recommendation deep links | Done locally for template outputs and VGI links | Step output generated from configured template is editable/versioned/exportable; stale edits refused; VGI gap links to a mapped step or anchored provisional curriculum location |
| VB-05 | P0 | Seed/version management for provisional curriculum and full 24-step journey regression | Done locally; seed/admin path and full journey verified | Seed creates a draft only; permission-gated publication validates under lock and enforces 6/24; new drafts copy published versions; test completes and validates all 24 steps while methodology stays provisional |
| INTEL-01 | P1 | Rules-first Venture Intelligence Engine across VGI, builder, challenges, knowledge and services | Partial; adds open opportunity recommendations from the existing startup eligibility rules alongside VGI-to-Builder, visible Knowledge and published Challenge links; persisted accept/dismiss feedback; selected startup only | useful fallback without provider; explainable eligibility reasons and unknowns; selected venture context only; no cross-venture context leakage |
| CH-01 | P1 | Challenge moderation lifecycle, evidence, revisions, filters, bookmark/interest | Moderation UI and primary workflows tested; still partial | expand cross-role workflow/UAT; approved published snapshots only; proposal visibility follows active startup membership; private evidence download restricted to submitter and challenge staff; unsafe link/unsupported upload rejection covered locally |
| KH-01 | P1 | Knowledge repository, taxonomy/licensing/visibility, keyword search, bookmarks/download tracking | Partial | restricted resources excluded; keyword fallback works without semantic service |
| EXP-01 | P1 | Expert profiles/categories/verification, bookings, conflict/timezone, messaging, contracts and sandbox ledger | Partial; payment terms/provider blocked for production | private messaging and booking access tests; no gateway settlement claims |
| CAP-01 | P1 | Capital diligence checklist/document request, scoped data-room grants, investor pipeline history/report | Partial | granular consent and expiry/revocation enforced; no unrelated startup access |
| ANA-01 | P1 | Founder, institution, investor and admin role dashboards for new modules | Partial | authorized data only; metric definitions, freshness and export parity tested |
| IMS-01 | P1 | Preserve registry/IMS boundary and verify both journeys when approved integration environment exists | Local NSP side tested; staging blocked | stable canonical `startup_uuid`; no reviewer notes/scores exposed; idempotency/reconciliation evidence |
| OPS-01 | P2 | Container/deploy hardening, API docs, backup/restore, accessibility/security/UAT and training artifacts | Docker files present; deployment target and environment blocked | reproducible build, health check and staging evidence; no production release without signoff |

**Owner gates:** Do not make provisional builder/VGI content official, activate real payments, expose investor diligence material, choose production infrastructure or change IMS production data without the corresponding owner approval.

IMS items (marked **IMS**) change the IMS codebase and need separate owner authorization before work starts.

## Phase 1 (reference)

Implemented in Phase 1 (see `phase-status.md`): Laravel foundation, RBAC, public CMS (pages, revisions, posts, media, menus, FAQs, testimonials, partners, SEO redirects), contact form, branding settings, feature flags, audit and login logs, security headers, OIDC client behind flag, `StartupRegistry` disabled adapter. Not deployed.

## Phase 2: Unified identity and registry

| ID | Title | Depends on | Est | Owner | Acceptance criteria |
|---|---|---|---|---|---|
| NSP-201 | Connect NSP to the chosen IdP (staging) | D1, D9 | M | security | Login via staging IdP passes every check in `oidc-sso.md` §4; negative tests for bad iss, aud, azp, exp, nonce, signature, state. |
| NSP-202 | RP-initiated logout and session handling | NSP-201 | S | security | Logout ends NSP session and IdP session; returns to configured URI; test covers missing `id_token_hint`. |
| NSP-203 | Admin MFA enforcement via IdP | NSP-201 | S | security | Users with admin roles are refused if the token lacks the agreed `amr`/`acr` value. |
| NSP-204 | Manual account-linking screen for admins | NSP-201 | S | backend | Admin can link a local account to `(iss, sub)` after a check; action audit-logged; email auto-link stays off. |
| REG-205 | Registry module skeleton (per D2) with migrations from `erd.md` (a) | D2 | L | backend | Tables, constraints and unique active membership enforced; migrations run up and down on a clean DB. |
| REG-206 | Registry API: startups GET/POST/PATCH with ETag and Idempotency-Key | REG-205 | L | backend | Matches `openapi.yaml`; contract tests pass; stale If-Match returns 412; repeated key returns same result. |
| REG-207 | Field-level read/edit policy and provenance | REG-206 | M | backend | Hidden fields omitted; forbidden edit returns 403 with field list; each change writes provenance row. |
| REG-208 | Memberships and invitations | REG-206 | M | backend | Invite, accept, revoke; owner cannot be invited; expired invite rejected; audit entries written. |
| REG-209 | Consent grant, change, revoke + `registry.consent.revoked.v1` | REG-206, D4 | M | backend | Revocation stops API disclosure at once (test); event written to outbox in same transaction. |
| NSP-210 | HTTP `StartupRegistry` adapter replacing disabled one | REG-206 | M | backend | Flag `registry` switches adapter; registry outage shows a clear message, no 500. |
| NSP-211 | Startup selector and active-startup scope | NSP-210 | M | backend | Founder of two startups can switch; changing UUID in URL to a non-member startup returns 403/404 (spec §12 test). |
| NSP-212 | Startup onboarding wizard (core profile) | NSP-210, D4 | L | frontend | Create or join startup without duplicates; consent texts versioned; WCAG keyboard and label checks pass. |
| REG-213 | Duplicate check on create + duplicate review queue | REG-206, D3 | M | backend | Strong-evidence match returns 409 with candidates; staff queue lists cases; names alone never block or merge. |
| QA-214 | Phase 2 gate tests: IMS-first and NSP-first identity journeys (NSP side, IMS mocked) | NSP-211, REG-213 | M | QA | Same startup UUID across journeys; no duplicate company; evidence recorded in `testing-uat.md`. |

## Phase 3: IMS integration

| ID | Title | Depends on | Est | Owner | Acceptance criteria |
|---|---|---|---|---|---|
| IMS-301 | **IMS** OIDC client and `(iss, sub)` columns | NSP-201, owner authorization | L | security | IMS login via IdP passes the same checklist; existing users linked after sign-in, not by email. |
| IMS-302 | **IMS** route mount and API auth layer (`/ims/api/v1`) | IMS-301, U2 | M | backend | ModuleRegistry entries added; token audience checked; unauthenticated calls return 401 JSON. |
| IMS-303 | **IMS** `me/applications`, `me/programs`, `programs` endpoints with field allow-list | IMS-302 | M | backend | Contract test fails if reviewer comments, scores or AI review fields appear. |
| IMS-304 | **IMS** prefill from registry and immutable application snapshot | IMS-302, REG-207 | L | backend | Founder sees prefilled editable fields; snapshot stored at submit; later registry edits do not change it. |
| INT-305 | Outbox tables and cron delivery command (NSP and registry) | REG-205 | M | backend | Outbox row written in business transaction; cron run respects batch and time budget; no overlap. |
| INT-306 | HMAC signing, timestamp window, idempotent inbox | INT-305 | M | security | Tampered body → 401; timestamp > 300 s → 401; duplicate event_id → 200, no duplicate record. |
| IMS-307 | **IMS** cron outbox and inbox scripts | INT-306, IMS-302 | M | backend | Same tests as INT-306 pass against IMS; works without queue workers. |
| INT-308 | Retry backoff, dead letter, admin replay | INT-305 | M | backend | Backoff schedule applied; row dead after 10 tries; admin replay keeps event_id; alert sent. |
| INT-309 | Nightly reconciliation and `sync-status` endpoint | INT-308 | M | backend | Simulated missed event is repaired by the next run and logged. |
| NSP-310 | Founder workspace: My Applications / My Programs summaries | IMS-303 | M | frontend | Shows summaries and deep links only; IMS outage shows "unavailable"; validated return URLs. |
| DAT-311 | Legacy migration dry-run (alias mapping, candidate report) | REG-205, D3, U1, U5 | L | data | Runs on a DB copy only; CSV report; row counts reconcile; nothing written to registry. |
| DAT-312 | Duplicate review screen and audited merge/unmerge | DAT-311 | L | data | Staff accept/split/reject groups; each merge has an unmerge from the journal; tested rollback. |
| QA-313 | Phase 3 gate: sync, consent revocation and failure recovery tests | INT-309, DAT-312 | M | QA | Spec §12 integration cases pass, including registry outage and replayed webhook. |

## Phase 4: Growth and VGI

| ID | Title | Depends on | Est | Owner | Acceptance criteria |
|---|---|---|---|---|---|
| VGI-401 | VGI framework admin: dimensions, questions, options, weights, versions, approval | NSP-211 | L | backend | Published version is read-only; new edits create a new version; approval audit-logged. |
| VGI-402 | Assessment taking and evidence upload | VGI-401 | M | frontend | Saves drafts; resumes; evidence files private to startup members. |
| VGI-403 | Deterministic scoring engine and stage classification | VGI-401 | M | backend | Same answers + version → same score (unit tests); human override needs reason and is audited. |
| VGI-404 | Growth plans, goals, actions, progress logs | VGI-403 | L | backend | Scoped to active startup; members only; exportable to PDF. |
| VGI-405 | Growth dashboard and rules-based recommendations v1 | VGI-404 | M | frontend | Each recommendation shows its reason; no AI; empty and error states covered. |

## Phase 5: Ecosystem services

| ID | Title | Depends on | Est | Owner | Acceptance criteria |
|---|---|---|---|---|---|
| CAP-501 | Investor onboarding and verification | D5 | M | backend | Unverified investors see public fields only; verification by ecosystem_manager audited. |
| CAP-502 | Funding requests and data room with per-investor grants | CAP-501 | L | backend | Investor without a grant gets 403 on pitch deck (spec §12); signed expiring URLs. |
| CAP-503 | Matching with explainable factors and introductions | CAP-502 | L | backend | Match shows factors; introduction needs founder consent. |
| EXP-504 | Expert profiles, booking requests, mentorship sessions | NSP-211 | L | backend | Session notes visibility rules enforced; feedback recorded. |
| TAL-505 | Vacancies, candidate profiles with consent, applications pipeline | NSP-211 | L | backend | Candidate data visible only to the employer applied to; retention placeholder honoured. |
| OPP-506 | Opportunities, events, bookmarks, deadlines, moderation | NSP-211 | M | backend | Expired opportunities hidden; moderation queue for submitted listings. |
| MKT-507 | Marketplace listings and inquiries | OPP-506, D6 | M | backend | Inquiry messaging between parties only; admin dispute flag. |
| QA-508 | Phase 5 role-based UAT | CAP-503 to MKT-507 | M | QA | UAT scripts signed off per role. |

## Phase 6: Communications and intelligence

| ID | Title | Depends on | Est | Owner | Acceptance criteria |
|---|---|---|---|---|---|
| COM-601 | Notification centre, templates and preferences | D7 | M | backend | Per-type preferences honoured; unsubscribe for non-transactional email. |
| COM-602 | Email delivery via scheduler, retries and delivery log | COM-601, D10 | M | DevOps | Works with cron-only hosting; failures retried and logged. |
| ANA-603 | Role-aware dashboards and KPI definitions | VGI-405, QA-508 | L | backend | KPIs match documented definitions; IMS figures aggregate-only. |
| ANA-604 | Exports with tenant scope and audit | ANA-603 | M | backend | Export contains only scoped data; each export audit-logged. |
| AI-605 | Optional AI assistant behind `ai_assistant` flag | ANA-603, legal review | L | backend | Consent, rate limits, provenance labels, no confidential documents sent; prompt-injection tests. |

## Phase 7: Production readiness

| ID | Title | Depends on | Est | Owner | Acceptance criteria |
|---|---|---|---|---|---|
| OPS-701 | Remote repo, CI (tests, `composer audit`, asset build) | M7 | M | DevOps | CI runs on every push; built assets deployed without Node on server. |
| OPS-702 | Staging environment and deployment runbook | OPS-701, D9, D10 | M | DevOps | `deployment.md` steps reproduce staging from scratch; rollback tested. |
| OPS-703 | Backups and restore drill | OPS-702 | S | DevOps | Restore from backup to a clean server succeeds and is timed. |
| OPS-704 | Laravel 13 upgrade (if D11 approved) | D11 | M | backend | Full test suite passes on PHP 8.3+ staging. |
| SEC-705 | Security review and penetration test | OPS-702 | L | security | Findings triaged; high and critical fixed and retested. |
| A11Y-706 | Accessibility audit (WCAG 2.1 AA target) | OPS-702 | M | QA | Automated and manual results recorded; no claim of compliance without evidence. |
| PRV-707 | Legal/privacy review and retention jobs | D4, D8 | M | PM | Review notes filed; purge jobs implement approved periods. |
| PERF-708 | Performance and load test on staging | OPS-702 | M | QA | Agreed response-time targets met on low-bandwidth profile. |
| DOC-709 | Admin and startup user manuals, operations runbook | OPS-702 | M | docs | Manuals cover each released role; runbook covers incident steps. |
| REL-710 | Go-live checklist and stabilisation | all Phase 7 items | M | PM | Signed go-live checklist by owner. |
