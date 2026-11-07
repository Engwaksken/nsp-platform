# Owner-alignment gap matrix

**Audit date:** 7 November 2026
**Branch:** `owner-alignment`
**Evidence basis:** repository inspection and `php artisan test` in this workspace. No staging/production systems were accessed.

Statuses describe current local code: `implemented+tested`, `implemented+untested`, `partial`, `missing`, `blocked`. Modules with new files in the working tree are not presumed complete merely because code exists.

| ID / requirement | Existing evidence | Status | Remaining gap / risk | Next backlog action | Acceptance evidence |
|---|---|---|---|---|---|
| M01 Identity, orgs, tenant access | OIDC/MFA, canonical UUID, memberships, consent and audit in `app/Registry`, `app/Services/Oidc`; `app/Organizations`, migration and routes | partial; focused regression coverage added | Tests cover invite email matching, expiry, decline/reinvite stale-token invalidation, self-leave, last-admin and self-role protections, suspension, role/scope gating, tenant 404 isolation, scope narrowing and immediate denial of revoked/expired records. Repository search found no other direct portfolio consumers beyond the organization portal; further work: manual UX/UAT and production IMS IdP remains externally dependent | ORG-01: manual UX/UAT; verify admin lifecycle on staging | Keep consumers behind `PortfolioAccess`; retain registry tests |
| M02 Venture Builder (6 × 24) | Curriculum service/seeder/admin routes, `app/Builder/VentureBuilderService.php`, workspace controller/views, provisional curriculum | implemented+tested (local) | Official labels/methodology still await owner approval; deployment and manual UAT remain | Owner methodology review; staging UAT | Draft-only idempotent seed, invalid-count publish rejection, permissioned publish/new-version flow and full 24-step submit/validate journey; full suite 233 passed |
| M03 Ten-dimension VGI | `app/Growth/OwnerDimensions.php`, scoring/service/models, migration, provisional draft and dimension trend UI | implemented+tested (local) | Weights, questions and stage bands are provisional; owner readiness thresholds remain unconfigured; owner signoff still required | Obtain methodology signoff; preserve not-comparable state across rubric versions | Scorer 8 passed; Growth 10 passed; same-rubric trends compare dimensions; missing evidence and unsupported readiness are explicit |
| M04 Venture Intelligence Engine | Existing `app/AI` writing assistant; new `app/Intelligence` code | partial | Existing AI is optional and locked; deterministic cross-module guidance, feedback, versioned documents and scoped context need verified flows | INTEL-01 | Rule fallback, VGI-to-builder link, feedback and tenant isolation tests |
| M05 Challenge Exchange | New `app/Challenges`, controllers, views, migration and routes | partial | Verify moderation/versioning/filtering and public-only published visibility end-to-end | CH-01 | Draft/review/publish/revise/publication-access feature tests |
| M06 Knowledge Hub | New `app/Knowledge`, models, migration and routes | partial | Verify repository UX, license/visibility, search fallback, bookmarks/download tracking and restricted exclusion | KH-01 | Search and role/visibility tests, bookmark/download behavior |
| M07 Expert Marketplace | Existing expert engagements plus new booking, availability, messaging, contracts, payment models/services | partial | Integration surface appears staged but no evidenced end-to-end tests found in baseline suite; gateway remains sandbox/mock pending owner terms | EXP-01 | Booking conflict/time-zone, messaging authorization, completed-only review, idempotent mock ledger tests |
| M08 Capital Exchange | Existing funding/data-room/matching plus new `app/Capital`, migration, controller | partial | Due diligence, scoped grants, pipeline history and report flows need end-to-end tests | CAP-01 | Grant expiry/revocation, unrelated startup isolation, audited pipeline tests |
| M09 Stakeholder dashboards/analytics | Existing founder/investor/admin analytics, KPIs and exports; organization module new | partial | Institution portfolio dashboard and new module queues/metrics absent or unverified; metric definitions/low-count protections need review | ANA-01 | Fixture reconciliation, tenant-safe filters/exports and role dashboards |
| Single startup identity / Hive IMS | `app/Registry`, `app/Services/Registry`, integration docs and feature tests | implemented+tested (local) | SSO/registry production endpoints and actual IMS deployment are not confirmed; preserve canonical UUID and consent | INT-IMS-01 | Existing registry/integration suites; staged joint smoke test blocked on environment/owner |
| Deployment / delivery | New `Dockerfile`, compose and deploy files; existing operations docs | partial | No container build/health/restore evidence or hosted environment; deployment target is owner decision | OPS-01 | Build and smoke test container, document target, staged restore drill |

## Baseline verification

Command: `php artisan test` (7 November 2026), after VGI fixture alignment: **224 passed (1537 assertions)**. Focused `php artisan test --filter=GrowthTest`: **9 passed (79 assertions)**. Local-only evidence; no deployment or owner approval implied.

## Decisions still requiring owner input

Official builder labels/content; VGI questions, weights, cutoffs and readiness mapping; organization access/consent policy; AI provider and data handling; payment provider/fees/refunds/contracts; investor diligence obligations; cloud/search/storage target; branding; commercialization-rate definitions; warranty/support duration; IMS/registry operational ownership. Until decided, implementation content and financial workflows must remain clearly provisional/sandboxed.
