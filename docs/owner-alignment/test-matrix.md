# Owner-alignment test matrix

**Date:** 7 November 2026. These are local test-run results only; no staging or production verification is implied.

| Area | Test / evidence | Result | Follow-up |
|---|---|---|---|
| Existing platform, registry/IMS, security, ecosystem, operations | `php artisan test` | 224 passed (1537 assertions) | Local-only evidence; no staged/deployed claim |
| VGI owner rubric | `php artisan test --filter=GrowthTest` | 9 passed (79 assertions) | Questions, weights, bands and thresholds remain provisional |
| VGI seed alignment | `database/seeders/VgiStarterSeeder.php` loads `vgi_owner_dimensions_draft.php` | Implemented and covered by Growth suite | Add dedicated insufficiency/readiness edge cases |
| Venture Builder curriculum invariant | `php artisan test --filter=CurriculumDefinitionTest` | 2 passed (6 assertions): provisional curriculum has 6 active modules/24 active steps; invalid step count blocked | Runtime journey, review, evidence, versioning and isolation tests remain |
| Venture Builder journey/review/evidence/documents | `php artisan test --filter=VentureBuilderTest` | 5 passed (54 assertions): enrollment/resume, prerequisite locks, stale writes, review/revision, private evidence, template generation/edit/version/export and stale document edit refusal | Curriculum admin/versioning and full 24-step journey remain |
| VGI → Builder mapping | `php artisan test --filter=GrowthTest` | 9 passed (80 assertions), including gap-to-dimension mapped link | Link is a navigation aid; content remains provisional |
| Full suite | `php artisan test` | 231 passed (1598 assertions) | Local-only; no staging/deployment claim |
| Organizations | Existing feature suite had no identified coverage in baseline run | Not verified | Add authorization, portfolio scope and immediate revocation tests |
| Intelligence, challenges, knowledge | Existing feature suite had no identified coverage in baseline run | Not verified | Add fallback, moderation, restricted search, feedback and cross-tenant tests |
| Expert and capital additions | Legacy expert/funding tests pass; new paths not evidenced by those tests | Partial | Add booking/payment sandbox and diligence/pipeline access tests |
| Dashboards | Legacy analytics tests pass | Partial | Add organization/institution and new queue metric fixture reconciliation and export parity |
| Deployment | Docker/config files present in working tree | Untested | Build container and smoke test only in safe local environment; staging awaits owner environment decision |

## Baseline failure detail

The initial suite had 218 passes and 6 Growth failures: the seeded rubric had legacy dimensions and tests expected legacy results without required evidence. Seeder and fixtures were aligned to the provisional ten-dimension rubric without weakening dimension/evidence validation. Current evidence: focused suite 9 passed; full suite **224 passed (1537 assertions)**. Provisional scoring settings are not owner-approved.
