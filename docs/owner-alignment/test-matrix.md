# Owner-alignment test matrix

**Date:** 7 November 2026. These are local test-run results only; no staging or production verification is implied.

| Area | Test / evidence | Result | Follow-up |
|---|---|---|---|
| VGI-alignment full suite checkpoint | `php artisan test` after VGI test alignment | 224 passed (1537 assertions) | Superseded by the later full-suite run below |
| VGI owner rubric | `php artisan test --filter=GrowthTest` | 10 passed (87 assertions) | Questions, weights, bands and thresholds remain provisional |
| VGI seed alignment | `database/seeders/VgiStarterSeeder.php` loads `vgi_owner_dimensions_draft.php` | Implemented and covered by Growth suite | Owner signoff for methodology remains pending |
| VGI insufficiency/readiness/evidence | `php artisan test --filter=ScorerTest` | 8 passed (27 assertions): null aggregate below coverage threshold, explicit insufficient dimensions, readiness not-configured, required evidence adequacy without score inflation | Methodology weights/cut-offs still await approval |
| VGI dimension trends | `php artisan test --filter=GrowthTest` | 10 passed (87 assertions): same-rubric dimension change shown; different rubric versions explicitly not comparable | Local functional test only |
| Venture Builder curriculum invariant | `php artisan test --filter=CurriculumDefinitionTest` | 2 passed (6 assertions): provisional curriculum has 6 active modules/24 active steps; invalid step count blocked | Runtime journey, review, evidence, versioning and isolation tests remain |
| Curriculum seeding, admin publishing and complete journey | `php artisan test --filter=BuilderCurriculumPublishingTest` | 2 passed (21 assertions): idempotent draft-only seed, malformed 23-step draft refused, authorized admin publish, new draft version, all 24 steps submitted and validated to 100% | Staging and owner approval not performed |
| Venture Builder journey/review/evidence/documents | `php artisan test --filter=VentureBuilderTest` | 5 passed (54 assertions): enrollment/resume, prerequisite locks, stale writes, review/revision, private evidence, template generation/edit/version/export and stale document edit refusal | Curriculum admin/versioning and full 24-step journey remain |
| VGI → Builder mapping | `php artisan test --filter=GrowthTest` | 9 passed (80 assertions), including gap-to-dimension mapped link | Link is a navigation aid; content remains provisional |
| Full suite | `php artisan test` | 240 passed (1685 assertions) | Local-only; no staging/deployment claim |
| Organization portfolio and membership lifecycle | `php artisan test --filter=PortfolioAccessTest` | 4 passed (49 assertions): scope/role boundaries; expiry/suspension/revocation; cross-tenant 404; scope narrowing; wrong-email and single-use invitation routes; self-role changes and last-admin departure blocked; member self-leave succeeds | Local functional tests; no staging/UAT evidence |
| Intelligence, challenges, knowledge | Existing feature suite had no identified coverage in baseline run | Not verified | Add fallback, moderation, restricted search, feedback and cross-tenant tests |
| Expert and capital additions | Legacy expert/funding tests pass; new paths not evidenced by those tests | Partial | Add booking/payment sandbox and diligence/pipeline access tests |
| Dashboards | Legacy analytics tests pass | Partial | Add organization/institution and new queue metric fixture reconciliation and export parity |
| Deployment | Docker/config files present in working tree | Untested | Build container and smoke test only in safe local environment; staging awaits owner environment decision |

## Baseline failure detail

The initial suite had 218 passes and 6 Growth failures: the seeded rubric had legacy dimensions and tests expected legacy results without required evidence. Seeder and fixtures were aligned to the provisional ten-dimension rubric without weakening dimension/evidence validation. The current full suite result is **233 passed (1619 assertions)**; focused Growth suite is 9 passed. Provisional scoring settings are not owner-approved.
