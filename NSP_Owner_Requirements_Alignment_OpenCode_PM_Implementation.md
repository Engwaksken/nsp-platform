# HIVE NSP — OWNER REQUIREMENTS ALIGNMENT & OPENCode PM IMPLEMENTATION ORDER

**Status:** Updated implementation directive for an NSP project already under development. Not a statement of implemented features.  
**Prepared:** 7 November 2026  
**Inputs:** (A) Owner's “Design, Development, Testing, Deployment and Support of NSP — An Intelligent, Data-Driven Venture Intelligence Platform for Venture Building and Commercialization”; (B) earlier `Hive_NSP_OpenCode_PM_Full_Development_Specification.md`; (C) user's instruction that OpenCode PM has already started NSP development.  
**Audience:** OpenCode PM, delegated engineering/QA agents, Hive project owner and technical reviewers.  
**Priority:** Treat this document as an additive owner-alignment change request. Where the owner's document adds requirements, include them; where architecture remains compatible, retain it. Escalate actual conflicts, do not discard existing working code.

---

## 0. COPY-PASTE DIRECTIVE TO OPENCode PM

> You are the existing NSP project manager. DEVELOPMENT IS ALREADY UNDERWAY. DO NOT restart, re-scaffold, delete, or blindly rewrite the application. First audit the current repository, routes, migrations, model relationships, front-end screens, test suite, APIs, dependencies, deployment settings, and recent git history. Compare existing implemented functionality against this entire updated owner-approved scope and the previous Hive IMS–NSP specification. Produce an evidence-based gap matrix with statuses `implemented+tested`, `implemented+untested`, `partial`, `missing`, `blocked`, `not applicable`, including file paths, test evidence, risks, dependencies, estimates, and acceptance criteria.
>
> Extend NSP into **an intelligent, data-driven venture intelligence platform for venture building and commercialization**. It must support the continuous journey **Assess → Identify Gaps → Guide → Execute → Validate → Improve → Scale**. Implement all nine owner modules: (1) Identity and multi-tenant organization management, (2) six-module/24-step Venture Builder, (3) ten-dimension VGI assessment, (4) Venture Intelligence Engine, (5) Challenge Exchange, (6) Knowledge Hub, (7) Expert Marketplace, (8) Capital Exchange, and (9) role-specific dashboards and analytics. Preserve earlier requirements for one Hive startup identity, SSO with IMS, canonical shared company profile, existing NSP ecosystem/talent/opportunities features, and secure API integration. Implement working end-to-end flows, not placeholder cards.
>
> Start immediately with read-only audit, gap analysis, acceptance tests and architecture adjustments. Implement unblocked features incrementally with feature flags and migration-safe changes. Seek owner approval only for material decisions that cannot safely be inferred, such as definitive 24-step names, VGI weights and thresholds, commercial payment/fee terms, data-sharing policy, infrastructure, and branding. While these await approval, use configurable metadata, adapters, seed templates flagged as provisional, and testable rule-based implementations. NEVER assert an AI model has been trained, a payment has settled, an integration exists, or production deployment has occurred unless evidence confirms it. Never commit secrets or use real customer data in tests. Do not alter IMS production data without separately authorized change management.

## 1. CHANGE IMPACT — WHAT THE OWNER ADDED VS THE PREVIOUS BRIEF

| Owner module/requirement | Previous brief coverage | Mandatory alignment action |
|---|---|---|
| Unified identity, RBAC, organization management, multi-tenancy, 2FA, audit | Shared identity/startup registry and RBAC already specified | Preserve; expand organization membership/tenant scope to universities, ESOs, investors, partners, corporations; MFA/audit acceptance tests |
| **Venture Builder: six sequential modules and exactly 24 structured steps** | General growth plans/roadmaps, not the complete prescribed builder | **NEW CRITICAL:** configurable step engine, evidence, checklists, generated documents, dependencies, progress, validation and VGI triggers |
| **VGI: ten named dimensions** | General configurable VGI present | **EXPAND:** exact owner dimensions, overall/category scores, stage, commercialization and investment readiness, repeat assessments, versioning/explanations |
| Venture Intelligence Engine | Optional/general AI and recommendations | **EXPAND:** contextual guidance, market/customer personas, business/financial/commercialization advice, safe document creation, ML integration and quality evaluation |
| **Challenge Exchange** | Generic opportunities and CMS | **NEW:** curated challenge lifecycle, research/policy evidence, review, publication, revisions, filters |
| **Knowledge Hub** | Content/CMS resources | **EXPAND:** resource repository, semantic search, personalized discovery, bookmarking and download tracking |
| Expert Marketplace | Experts and booking proposed | **EXPAND:** researchers, legal/finance/developer/design/marketing categories, messaging, ratings, packages, contracts, payment integration |
| Capital Exchange | Investor/funding module proposed | **EXPAND:** diligence workflow, investment-readiness reports, investor pipeline, VGI/performance-supported matching |
| Tailored dashboards | Founder/investor/admin plus ecosystem dashboards | **EXPAND:** institutional portfolio analytics, commercialization rates, sector insights, program outcomes; investor pipeline and role-specific metrics |
| Cloud-native modular deployment | API-first, queues, hosting checks | **EXPAND:** containers, object storage, search, analytics pipeline, horizontal scalability design, availability/recovery |
| Formal delivery/training/support | High-level earlier | **EXPAND:** design/ERD/API/source/test reports/user manuals/training/knowledge transfer/warranty support artifacts |

**Retain, not remove:** IMS/NSP integration, canonical `startup_uuid`, shared profile, IMS application snapshotting, consent, SSO, role-based program access, talent/job opportunities, startup funding needs, events, content CMS, accessibility, security, and audit records. The owner's nine functional modules are a **minimum scope**, not permission to discard compatible earlier modules.

## 2. DO THIS FIRST: CURRENT-STATE AUDIT WITHOUT REBUILDING

1. Identify the actual NSP project root (do not guess) from current OpenCode workspace/git remote; verify which branch is checked out and preserve uncommitted changes.
2. Read README, `composer.json`, `package.json` if any, `.env.example` (never print `.env` secrets), routes, controllers, policies, jobs, events, services, migration files, config, views/components, tests, and CI/deployment definitions.
3. Inspect database schemas read-only using approved local/staging connection only. Record existing sample-safe table metadata, indexes and foreign keys, not production personal data.
4. Identify usable modules, broken screens, placeholder buttons, duplicate registration flows, existing IMS API adapters, and actual SSO configuration. Do not infer completion from filenames.
5. Run existing lint/static checks/tests on a safe branch; record actual test results and blockers.
6. Compare each requirement below with concrete code + passing feature-test evidence. Create `docs/owner-alignment/gap-matrix.md` and `docs/owner-alignment/current-state.md`.
7. Update architecture and dependency diagram; prepare additive migrations; create safe branch and checkpoints before implementing changes.
8. Publish a short change-impact summary for the owner and technical supervisor. Proceed with unblocked implementation rather than waiting for every commercial/methodology decision.

**Gap matrix columns:** requirement ID | owner source | current implementation and file paths | actual behavior tested | gap | severity | owner decision needed | planned change | tests | responsible agent | status | evidence/date.

**Do not claim a repository audit has been performed by ChatGPT:** only PM has access to the code and must verify every claim.

## 3. PRODUCT DEFINITION AND SYSTEM BOUNDARIES

NSP is a multi-stakeholder venture-building and commercialization environment serving entrepreneurs, universities, Entrepreneurship Support Organizations (ESOs), investors, development partners, corporate innovation teams, mentors/consultants and administrators. End-to-end venture life cycle: **Assess → Identify Gaps → Guide → Execute → Validate → Improve → Scale**.

**Hive IMS** owns applications, program questions, reviewer scores/comments, selection, cohorts, attendance, milestones, grants and program-specific reports/docs. **Hive Startup Registry** owns the canonical startup identity and the reusable core company profile, with stable `startup_uuid`, memberships and consent. **Hive NSP** owns VGI history, Venture Builder state, growth/recommendations, challenges, knowledge, expert engagements, capital activities and longitudinal venture outcomes. **Identity Provider** owns sign-in/MFA and identity subjects. Never confuse an IMS program reviewer score with the NSP VGI score.

NSP must support both entry journeys:
- **IMS first:** founder signs in once; create/link core startup record; keeps application snapshot in IMS; controlled opt-in/eligibility launches NSP onboarding without duplicate registration.
- **NSP first:** founder creates/joins canonical startup, completes VGI and Venture Builder; later applies to IMS with permitted profile prefill and editable application snapshot.

Use secure OIDC Authorization Code + PKCE with supported IdP, a registry API/event integration, signed/idempotent webhooks, retries/reconciliation and explicit field provenance. Cross-app **read-only** summaries can appear in founder navigation (My Applications/My Programs), but IMS remains the owner of those workflows. Do not copy passwords or expose reviewer comments.

## 4. TECHNICAL ARCHITECTURE — ADDITIVE AND DEPLOYABLE

- **Core:** Laravel 13 if installed environment/package compatibility is confirmed; PHP 8.3+; MySQL 8+ or existing compatible relational DB; enforce migrations and constraints. Follow existing frontend stack if viable (Blade/Livewire/Inertia); do not refactor purely for taste.
- **Bounded modules:** Identity/Organizations; Venture Registry Adapter; Venture Builder; VGI; Intelligence; Challenge Exchange; Knowledge Hub; Expert Marketplace; Capital Exchange; Talent/Opportunities (previous scope); CMS; Analytics; Operations.
- **Interfaces:** versioned JSON REST API/OpenAPI; Laravel jobs/events, policies, Form Requests, resources and domain services. Keep controllers thin. Define stable DTOs/ports for registry, IdP, AI provider, semantic index, document storage, notifications and payment provider.
- **Async:** queue-based AI/doc generation, event dispatch, indexing and webhooks; idempotency keys, exponential backoff, failure dashboard and retry controls. Make jobs tenant-safe.
- **Storage:** private object storage for startup evidence, investor diligence, contracts and generated docs (S3-compatible if approved), short-lived signed URLs; CDN only for approved public content; file type/size validation and malware scanning if available.
- **Search:** DB full-text first if needed; adapter for Meilisearch/OpenSearch or approved search service. Semantic search requires evaluated embeddings, scoped indexing and reindex jobs; no secret leakage to external vendors.
- **Scale:** containerized Docker images and deployment manifests for cloud target, health checks, stateless app instances, session/queue/cache externalization, scheduled jobs, database indexes, monitoring, backups. Support constrained cPanel deployment only if approved and documented as a capacity-limited fallback; do not falsely describe shared hosting as horizontally scalable.
- **Environment:** dev/staging/prod isolation, `.env.example`, secrets management, build pipeline, release rollback, data backup & restore drills; no breaking migration to production until approved.
- **Tenant model:** distinguish **institution/organization tenant** from **startup entity**; organizations manage entitled portfolios, not all startups. A startup can engage with multiple ESOs; define an explicit scoped `organization_startup_relationships` record and per-purpose access grant rather than forcing a startup to belong to only one ESO. Users can belong to multiple startups/organizations. Enforce tenant and object scoping at query/policy/export/job levels.

## 5. COMPLETE FUNCTIONAL MODULES — REQUIRED BEHAVIOR AND ACCEPTANCE

### M01 — User, Identity, Organization and Access Management

**Owner-required roles:** entrepreneur; mentor; consultant; investor; university; ESO; development partner; corporate innovation team; administrator. These may be organization types, not all login roles; model organizations separately and allow users multiple scoped memberships. Preserve expert, recruiter, content editor, platform support, auditor and IMS-specific roles where relevant.

Deliver: email verification, OIDC SSO, optional controlled registration/onboarding, MFA for elevated access and supported account flows, password recovery via IdP, profile management, startup/team invitation, organizations and memberships, roles and permissions, organization portfolio access requests/grants, revocation, activity/audit logs, consent/data access settings, admin suspension, tenant switcher and data minimization.

Acceptance: one login works cross-platform where configured; owner manages two ventures without leakage; university can only see authorized startups; revoked relationships stop access immediately; sensitive actions audited; privileged MFA enforced.

### M02 — Venture Builder: SIX sequential modules / TWENTY-FOUR steps (**NEW: CRITICAL**)

**Owner explicitly requires six sequential modules containing exactly 24 steps, but does not specify their official titles or per-step names.** Build a **metadata-driven builder** with exactly six active ordered modules and 24 active ordered step definitions in the approved published version. Do not present proposed step labels as owner-approved content. Put step titles, guidance, required fields, output templates, eligibility/unlock rules, VGI mapping, validation checklist, evidence requirements and content revisions in the database and CMS.

**Provisional structure for owner review only (4 steps per module; configurable):**

| Module | Proposed 4-step sequence (provisional, not from owner document) | Outputs |
|---|---|---|
| 1. Problem and Customers | Define problem; customer segment; stakeholder interviews; problem evidence | problem statement, interviews, validation summary |
| 2. Market Validation | market sizing; competitor mapping; customer discovery; market-validation conclusion | market map, competitor notes, validation brief |
| 3. Solution and Product | value proposition; solution concept; prototype/MVP; product testing | solution canvas, MVP plan, test results |
| 4. Business Model and Finance | revenue model; pricing; unit economics; financial assumptions | model canvas, costs/revenue worksheet |
| 5. Commercialization and Traction | go-to-market; sales experiments; partnerships/distribution; commercialization readiness | GTM plan, traction/evidence log |
| 6. Investment and Scale | team/operations; funding readiness; scaling roadmap; pitch/next milestones | readiness summary, roadmap, pitch materials |

These steps are **an implementation scaffold**, not a substitute for the owner's definitive methodology. Builder engine must allow changing labels/order/content before activation without changing code; version published curricula, and preserve historical step submissions when curricula change.

Each step MUST have: `not_started / in_progress / submitted / changes_requested / validated / completed` state; drafts/autosave; structured fields; source references; editable generated artifacts; private evidence attachments; reviewer/mentor feedback; named validator permissions; checklists; timestamps; status history; comments; step unlock logic; progress%; dependency/gap triggers from VGI; completion rules; revision history; PDF/Doc exports; owner-configured guidance and task suggestions. Avoid marking a step complete just for uploading a file when review is required.

Acceptance: test all 24 steps from start to finish with a seeded provisional version; resume a draft; revise a validated response; generate a versioned output; reject unauthorized cross-tenant read; VGI recommendation deep-links to required step; progress calculations and locks are correct; step history remains stable after curriculum revision.

### M03 — Venture Growth Index (VGI): TEN exact owner dimensions (**CRITICAL**)

Assessment dimensions must be stored using these exact names:
1. Problem Clarity
2. Customer Validation
3. Market Understanding
4. Product Readiness
5. Business Model Strength
6. Market Traction
7. Team Capacity
8. Financial Readiness
9. Scalability
10. Commercialization Readiness

Implement admin-controlled assessment questions, prompts, weight schema, evidence requirements, scoring rubric/anchors, input validation, rubric revision and preview. Provide individual dimension scores, overall score, strengths, gaps, evidence adequacy, trend across assessments, venture stage, investment readiness, commercialization readiness, suggested priorities and explicit recommendations to builder steps and ecosystem services.

**Critical methodology rule:** the source DOES NOT define numeric weights, 0–100 normalization, stage cutoffs or investment-readiness thresholds. Build **configurable published rubric versions** and block unapproved stage/readiness automation from being portrayed as authoritative. Suggested technical convention for QA: normalize sub-scores to 0–100 only after weights and mapping are signed off. Use clear `provisional` badge/label in admin and seeded demo assessments. Avoid simplistic claims that VGI is an objective prediction of startup success.

Scoring must be deterministic for fixed rubric + evidence, versioned, auditable and reproducible. If an expert override is permitted, require role, justification, who/when and original score; never erase original responses. Reassessment and progress plots show changes over time. Missing information should show an insufficiency state rather than manufactured certainty.

Acceptance: all ten dimensions present; assessment snapshot records version; reproducible totals; changed rubric does not rewrite old scores; missing evidence handled; owners can view scores, investors cannot see private details without grant; VGI recommends relevant Builder content.

### M04 — Venture Intelligence Engine (**EXPANDED CRITICAL**)

Develop a contextual recommendation/orchestration layer combining **rules + analytics**, with optional integration to approved ML/LLM providers. Deliver the following owner-required capabilities:
- Personalized venture guidance with actions, relevance reasons and source/evidence links.
- Market analysis and competitor/sector insights with disclosed source provenance and timestamp.
- Customer persona generation from supplied/approved data, visibly labeled as AI-assisted hypotheses requiring validation.
- Business model recommendations and assumption testing.
- Financial planning **support** (scenario and cashflow templates, assumptions, no guarantee or personalized regulated investment advice).
- Commercialization planning and milestone sequencing.
- Investment readiness assessment support tied to VGI and accessible metrics.
- Intelligent document generation for pitch outlines, plans, canvases, market briefs and reports.
- Persistent **authorized venture context** across sessions using versioned summaries and retrieval scoped to startup and user permissions; opt-in where appropriate.

Architecture: an `IntelligenceService` interface; tools/adapters; prompt/template registry with versioning; structured JSON output validation; sources/citations; uncertainty flags; content moderation; token/cost quotas; safe retries/fallback; consent and vendor retention reviews; evaluation fixtures; human review before investor-facing publication. Initially implement functional deterministic recommendation rules so every founder receives useful guidance without a paid model key; integrate external ML/LLM providers through secured feature flags.

Protect against prompt injection in imported content, private data leaks in retrieval, cross-tenant vector index results, unsanitized model HTML, and generated financial/business misinformation. Do not claim that continuous self-learning/ML training exists unless approved data governance, training, validation and monitoring are implemented. Capture consented feedback, explicit performance signals and model evaluations for future improvement.

Acceptance: VGI gap produces relevant builder recommendation; user can accept/dismiss and explain feedback; generated document is editable and versioned; provider downtime yields graceful non-AI guidance; AI data access and prompts are logged safely; no other startup's confidential data appears in generated output; search/source citations and owner approval before publishing external assertions.

### M05 — Challenge Exchange (**NEW MODULE**)

Admin/editor/institution moderated repository of innovation and market challenges with: problem description, sector, geography, market opportunity, supporting evidence, existing solutions, policy considerations, research references, priority level, submitter, tags, visibility, created/reviewed/published timestamps, revision/version history.

Workflow: draft → submitted → under_review → approved/rejected/changes_requested → published → archived. Authorized reviewers add comments, validate sources, reject insufficient evidence, and publish versioned changes. End users browse/filter by sector, location, priority, issuer and category; bookmark; express interest/submit proposed solution through a controlled intake flow; see provenance and last-reviewed date. Link relevant challenges to VGI, Venture Builder and experts, without implying a challenge has been validated solely because it was submitted.

Acceptance: public only sees published approved challenges; declined/private submissions not indexed; a revision preserves history; advanced search and pagination work; review decision is audited.

### M06 — Knowledge Hub (**EXPANDED**)

Repository for research publications, market intelligence, policy briefs/documents, templates, toolkits, learning resources, case studies, practical guides and business-development materials. CMS controls taxonomy, uploader/reviewer, content visibility/license, file, external links, publication/version/expiry status, metadata and SEO for public pages.

Deliver text/metadata search and filtering, bookmarks, download counters, recently viewed, related content, audience/stage recommendations, optional semantic/vector search with tenant filtering, content validity/review schedules, file previews where safe. External copyrighted material must be linked or licensed rather than ingested indiscriminately.

Acceptance: editors can draft/review/publish; logged-in users can bookmark/download resources they are allowed to access; semantic queries never return restricted documents; missing search service falls back to keyword search; recommendations are explainable.

### M07 — Expert Marketplace (**EXPANDED**)

Experts: mentors, consultants, researchers, legal advisers, financial specialists, developers, product designers, marketers and additional categories. Support identity/credential verification states, rich profiles, skills, sector, availability, rate/fee or pro bono status, languages, location/remote, documents, screening and admin approval.

Founder journey: filter/search → view expert → request/booking → schedule/time zone → private conversation → package/contract/terms acknowledgement → booking confirmation → session outcome, notes and follow-ups → permitted verified rating/review. Experts see own engagements and calendars, messaging, service packages, testimonials and payout status. Admin handles complaints, cancellation/refund policy settings, disputed reviews, abuse and moderation.

Payments: provider-agnostic integration interface; implement **sandbox/mock payment workflow** and transaction ledger first; integrate real gateway only after provider, currency, fees, refund, settlement and compliance approvals. Do not invent payment links or treat a locally marked `paid` status as a gateway-confirmed settlement. Contracts use approved templates/e-signature provider if authorized; otherwise record consent/acceptance and export documents without claiming legal e-signature compliance.

Acceptance: authorized search/booking/messages; conflicts and double bookings prevented; time zone DST tested; review restricted to eligible completed engagements; payment events idempotent/verified before paid state; users cannot read others' conversations or contracts.

### M08 — Capital Exchange (**EXPANDED**)

Startups: funding needs/profile, stage, instrument sought, amount/currency, use of funds, investor permissions, pitch deck, VGI-linked readiness, historical traction and consent-controlled data room. Investors: onboarding and verification, investment thesis, geography/sector/stage/ticket preferences, watchlists, sourcing pipeline, screening, notes and authorized portfolio analytics.

Support grant, debt, equity and accelerator opportunity listing as explicitly configured; funder matching (deterministic criteria first, explainability and feedback), expression of interest, intro requests, due-diligence checklist, document requests/review, stage pipeline (`sourced, screened, contacted, diligence, decision, committed, closed/declined` as configurable labels), reminders, permissioned sharing, investment readiness report and activity audit.

**Safety/control:** Matching is discovery, not proof of eligibility or finance approval. Do not expose founder personal details, sensitive revenue/financial data, decks, due diligence or nonpublic VGI detail without granular authorization. No automated investment decision or transfer of funds without separate explicitly approved financial/legal architecture.

Acceptance: founder controls investor access; investor cannot access unrelated startup data room; matching reasons reference authorized signals and versioned VGI; pipeline transitions and diligence requests audited; stage summaries/reports accurate.

### M09 — Dashboards, Analytics and Intelligence (**EXPANDED**)

**Founder:** current venture, stage, VGI per dimension and trends, 24-step Builder progress, validated milestones, recommendations, documents, challenges/bookmarks, funding readiness, expert bookings, talent/opportunities, authorized IMS applications/programs, upcoming tasks and alerts.

**Institution (universities/ESOs/development partners/corporations):** strictly entitled venture portfolio; startup progression; aggregate and cohort comparisons; commercialization rates with defined numerator/denominator and time period; sector/geography trends; program outcomes sourced from authorized IMS integration; reports/exports. Do not imply institution owns ventures that voluntarily engage elsewhere.

**Investor:** consented venture discovery, diligence requests, readiness summaries, deal pipeline, portfolio exposure, stage funnel, dashboards by sector/stage. Respect confidential data sharing.

**Administrator:** registration/usage, active startups, published content, challenge moderation queue, assessments and workflow health, engagement metrics, data quality, API/webhook errors, failed jobs, security/audit, AI cost/health, marketplace and payment status.

Implement time range filters, permission-safe CSV/PDF exports, clear metric definitions, low-count suppression where needed to protect identifiable data, data freshness indicators, zero-state handling, queued materialized aggregates if needed, charts and audit of sensitive exports.

Acceptance: aggregate values reconcile to source fixtures; cross-tenant isolation verified; institution shows only authorized portfolios; charts do not fabricate missing data; filtered exports match on-screen rules.

### M10 — Previously specified complementary NSP services (RETAIN)

Talent/recruitment and startup talent needs; jobs and applications; opportunities/events; startup announcements; ecosystem marketplace listings and partnerships; unified CMS and communications; support ticketing; profiles and multi-startup membership; website public pages, accessibility and settings. These features are **not explicitly detailed in the owner's latest nine-module text**; retain them as compatible prior requirements, subject to the project owner's roadmap prioritization, instead of silently dropping them. Reuse common opportunity catalogue to prevent duplicate modules, while preserving the separate Challenge Exchange moderation/data model.

## 6. PROPOSED DATABASE / MODEL CHANGES (DESIGN STARTING POINT, VERIFY WITH ACTUAL SCHEMA)

**Never duplicate canonical startup profile tables in NSP as independent competing masters.** Existing `startups` in NSP may be local projections keyed by canonical UUID, with provenance and no unsafe dual-master writes. Align names to existing migrations; avoid creating duplicate tables.

| Domain | Proposed core tables/entities | Essential constraints |
|---|---|---|
| Identity/organizations | `organizations`, `organization_memberships`, `organization_startup_relationships`, `startup_memberships` (registry reference), `access_grants` | unique identity/membership; scoped grants and revocation; no cross-tenant joins |
| Venture Builder | `builder_curricula`, `builder_modules`, `builder_step_definitions`, `builder_step_fields`, `builder_step_checklists`, `venture_builder_enrollments`, `venture_step_submissions`, `venture_step_responses`, `venture_step_evidence`, `venture_step_reviews`, `builder_documents`, `builder_step_events` | exactly 6 active modules/24 steps per approved curriculum; published versions immutable; progress unique per startup+curriculum+step |
| VGI | `vgi_rubric_versions`, `vgi_dimensions`, `vgi_questions`, `vgi_question_weights`, `vgi_assessments`, `vgi_responses`, `vgi_dimension_scores`, `vgi_overall_scores`, `vgi_evidence`, `vgi_override_logs`, `venture_stage_assignments` | versioned assessment snapshot; valid ranges; auditable overrides; no re-score mutation of prior results |
| Intelligence | `intelligence_context_versions`, `recommendation_rules`, `venture_recommendations`, `recommendation_feedback`, `ai_prompt_versions`, `ai_generation_jobs`, `ai_generated_documents`, `ai_evaluations`, `ai_usage_logs` | startup and user scoping; no raw secrets/PII in logs; quota and consent |
| Challenges | `challenges`, `challenge_versions`, `challenge_evidence`, `challenge_reviews`, `challenge_topics`, `challenge_bookmarks`, `challenge_solution_submissions` | review history; only approved published visible publicly |
| Knowledge | `knowledge_resources`, `resource_versions`, `resource_categories`, `resource_tags`, `resource_bookmarks`, `resource_downloads`, `resource_embeddings_index_metadata` | license/visibility, source attribution, published revision, scoped semantic indexing |
| Experts | `expert_profiles`, `expert_categories`, `expert_verifications`, `expert_services`, `expert_availability`, `expert_bookings`, `expert_conversations`, `expert_messages`, `expert_contracts`, `expert_reviews`, `payment_intents`, `payment_webhook_receipts` | booking conflict exclusion via transaction/locks; payment gateway idempotency; user access |
| Capital | `startup_funding_profiles`, `investor_profiles`, `investor_theses`, `funding_opportunities`, `investor_matches`, `investment_pipeline_items`, `due_diligence_checklists`, `data_room_files`, `data_room_grants`, `investor_interactions` | investor visibility grants and expiry; documented consent; pipeline event audit |
| Analytics | `metric_definitions`, `analytics_events`, `analytics_aggregates`, `report_definitions`, `export_jobs` | versioned denominator definitions, organization-scoped access |
| Shared operations | `audit_logs`, `feature_flags`, `integration_outbox`, `integration_inbox`, `integration_failures`, `system_health_checks`, `notification_templates`, `cms_pages`, `cms_revisions` | integration idempotency, retention, minimum permissions |

Every venture-bound row should contain `startup_uuid`, every tenant-bound row an appropriate `organization_id`/access relation (not necessarily a single tenant FK for cross-organization opportunities), and every privileged action should check explicit policies. Define indexing strategy for FKs, status, owner, timestamps, search and analytics. Enforce privacy-aware soft-deletion/retention schedules. Use encrypted private documents and secure references, not blobs in public tables.

## 7. RECOMMENDED INTERFACES & ROUTE CONTRACTS (PROPOSED, NOT CLAIMED EXISTING)

Align naming with existing routes; expose stable API resources using `/api/v1/` and OpenAPI. Examples:

| Purpose | Proposed API pattern |
|---|---|
| Current startup and authorized membership | `GET /api/v1/me/startups`, `GET /api/v1/startups/{uuid}` |
| Builder curriculum | `GET /api/v1/builder/curricula/current`, `GET /api/v1/startups/{uuid}/builder` |
| Builder step drafts/submissions | `PUT /api/v1/startups/{uuid}/builder/steps/{stepId}/draft`, `POST .../submit` |
| VGI rubric and assessment | `GET /api/v1/vgi/rubric/current`, `POST /api/v1/startups/{uuid}/vgi/assessments` |
| VGI trend | `GET /api/v1/startups/{uuid}/vgi/history` |
| Intelligence recommendations | `GET /api/v1/startups/{uuid}/recommendations`, `POST .../{id}/feedback` |
| Challenge exchange | `GET /api/v1/challenges`, `POST /api/v1/challenges`, `POST .../{id}/review` |
| Knowledge search | `GET /api/v1/knowledge/search?q=`, `POST /api/v1/knowledge/{id}/bookmark` |
| Expert booking | `GET /api/v1/experts`, `POST /api/v1/experts/{id}/bookings` |
| Capital and visibility | `GET /api/v1/startups/{uuid}/capital`, `POST /api/v1/data-room/{id}/grants` |
| Analytics | `GET /api/v1/organizations/{id}/analytics`, `GET /api/v1/admin/health` |
| Registry sync and IMS events | contract documented in `docs/ims-integration.md`, authenticated signed event endpoints |

Implement pagination/filtering, validator error formats, authentication & authorization, rate limits, idempotent write actions, version-aware conflicts, audit logging, API contract tests, and OpenAPI docs. **Do not put live sensitive profile data in public endpoints**.

## 8. CMS & INTERFACE REQUIREMENTS

Design an accessible, responsive professional SaaS experience with consistent page templates for founders, investors, experts, institutions and admins. Suggested layout: top-level Founder Dashboard tabs **My Applications, My Programs, My Startup Profile, My Growth Dashboard, Venture Builder, VGI, Funding & Investors, Experts, Talent, Opportunities, Challenges, Knowledge**; hide unauthorized destinations and enforce backend policies separately. Preserve existing layouts; standardize nav and route names rather than introducing duplicate sidebars.

CMS supports editable public homepage, about, audience-specific landing pages, challenges, knowledge pages, FAQs, partners, events, news, blog, contact, footer, policies, multilingual-ready content schema if approved, menus, logo/favicon, SEO metadata, redirects, social previews, media library, drafts/revisions/approvals and scheduled publishing where infrastructure permits. Visual identity tokens must be configurable; do not assume unapproved Hive branding. Prefer Poppins/Outfit only if approved/available and compatible with asset policy.

Target WCAG 2.1 AA verified through automated and manual testing, including screen readers, logical headings, contrast, focus order, skip links, accessible dialogs, table semantics, chart text equivalents, and keyboard-only flows. Optimize mobile and low-bandwidth UX; autosave and avoid data loss on long assessments.

## 9. SECURITY, PRIVACY, AI & PLATFORM GOVERNANCE

- Authenticate using supported OIDC IdP and authorization code+PKCE; MFA for privileged actors. Avoid custom cookie cross-domain hacks or sharing passwords between IMS/NSP.
- Least privilege, per-record `Policies`, protection against IDOR and cross-tenant exports; default private VGI/revenue/evidence and investor documents.
- Source-tagged, revocable, purpose-limited consent and data-sharing records; Uganda data privacy legal review and any applicable cross-border transfer review; data minimization, retention, access/export/delete workflows where lawful.
- TLS, secure cookies, CSRF, validated uploads, secure document scanning, HTTPS signed URLs, secrets outside git, rate limiting and content escaping/CSP.
- Security/privacy by design for AI: provider risk review, redacted prompts, no uploading private venture data to external AI without lawful approval, prompt-injection hardening, grounded outputs, clear AI disclosure, human review, tested evaluation, quota monitoring and failure fallback.
- Verify payment webhook signatures; never accept payment success from a browser callback alone; track refunds/disputes through provider reconciliation.
- Immutable/append-only audit for access grants, VGI overrides, publication approvals, investor file views, payment events and data exports.
- Separate integration failures, retry dead letters, health monitors, alerting and incident runbooks. Define backup retention, restore testing, RPO/RTO and admin operational responsibilities with owner.

## 10. DEVELOPMENT PLAN — CHANGE-AWARE, INCREMENTAL, WITH GATES

The owner's document groups delivery in four broad phases. These are preserved below, but a **Phase 0 audit** is added because coding has already begun. Parallelize independent modules once shared services are stable. Do not prematurely mark owner deliverables complete.

### Phase 0 — Reconciliation of work already done (FIRST)
- Snapshot git branch and schema; map code/test coverage to all requirements; architecture/dependency notes; owner decision register; baseline security/UAT gaps.
- Deliver: `current-state.md`, `gap-matrix.md`, prioritized issue backlog, updated ERD, API/SSO boundaries, implementation sequence; screenshots/test evidence.
- Gate: no data destruction; no conflicting master startup store; clear remaining work and approved exceptions.

### Phase 1 — Foundation + Identity + Venture Builder + VGI + Founder Dashboard (OWNER PHASE 1)
- Complete/reconcile registry/OIDC/RBAC and startup memberships; maintain IMS-compatible profile flow.
- Build 6-module, 24-step configurable Builder including evidence/documents/validations/progress.
- Build ten-dimension VGI with assessments, score versions, trend, next actions, stage/readiness (approved rules only).
- Founder dashboard integrating both; CMS/admin tools to manage builder curricula, scoring rubrics, permissions and content.
- Gate: IMS-first/NSP-first smoke tests; complete Builder journey, VGI sample assessment and next-action linkage; privacy/role tests; admin change without code deploy.

### Phase 2 — Venture Intelligence + Challenge Exchange + Knowledge Hub (OWNER PHASE 2)
- Start with testable rules engine; add approved ML/LLM integrations through adapters, evaluation and consent controls.
- Challenge moderation, evidence/source metadata, revisions, search.
- Knowledge resource CMS, bookmarks, download workflow, keyword and optional semantic search.
- Gate: contextual recommendations, safe generated drafts and tenant isolation; public-only published challenges; restricted resource search exclusion.

### Phase 3 — Expert Marketplace + Capital Exchange + Stakeholder Dashboards (OWNER PHASE 3)
- Expert verification/listing/booking/messages/packages/contract acknowledgement/reviews; sandbox payment gateway adapter, then approved live provider.
- Capital needs, investor profiles, matching, diligence and grant-controlled data rooms; pipeline.
- Institution/investor/admin reporting dashboards; retain existing talent/opportunity modules and connect to founder journey.
- Gate: end-to-end bookings, investor-introductions and restricted document grants; analytics correctness and RBAC acceptance.

### Phase 4 — Optimization + IMS Integration Completion + Analytics + Security + Go-live + Knowledge Transfer (OWNER PHASE 4)
- Finalize shared IdP/registry sync, event/idempotency/reconciliation, data migrations after dry-run; no program scoring leakage.
- Cloud/container release pipeline and scaling checks; backup/restore; WCAG/manual QA; security review; performance/load tests; model quality and cost evaluation; cross-device UAT.
- Prepare manuals, architecture diagrams, APIs, trainings, knowledge transfer, support/incident operating model, warranty defect workflow and formal owner go-live signoff.
- Gate: no critical security/tenant blockers; documented owner approvals; staging UAT and restore tested; monitoring and rollback prepared.

**Important sequencing exception:** IMS/registry architecture and essential identity integration must be designed and tested in Phase 0/1, not postponed until Phase 4. Phase 4 includes final integration hardening, migration and rollout only.

## 11. OWNER DECISION REGISTER — DO NOT FABRICATE SETTINGS

PM should seek and track decisions on: (1) official 6 module titles and 24 builder steps or approval of proposed scaffold; (2) ten-dimension VGI questions, weighting, thresholds, stage and readiness mapping, validation authority; (3) how an ESO/institution obtains startup portfolio access and data-sharing consent; (4) AI vendor, hosting/data region, retention, allowed sensitive-data processing, human review; (5) expert booking payment gateway, fees, commissions, taxes, refunds, contract wording and invoice policies; (6) investor diligence permission and legal obligations; (7) cloud provider/target, availability targets, SLA/RPO/RTO, object storage/search budget; (8) official branding/production domains; (9) semantic search corpus licensing; (10) precise commercialization rate and outcomes reporting definitions; (11) training/warranty duration and support coverage; (12) IMS/registry operational ownership and migration schedule.

Track `decision`, `provisional implementation`, `risk`, `needed by`, `approver`, `status`, `date`, `reference`. For anything unresolved, proceed with safe defaults in non-production and config flags, but never display provisional methodology as official, charge real money, release confidential investor data, or modify production infrastructure without authorization.

## 12. REQUIRED TEST MATRIX — EXPAND EXISTING SUITE

**Identity & IMS:** IMS-first and NSP-first no duplicates; same UUID; verified membership; consent/revocation; IMS snapshots unaffected by current profile changes; no reviewer note leakage; duplicate webhooks idempotent; 401/403/429 behavior; failed registry gracefully reconciles.

**Venture Builder:** exactly six published modules/24 steps; complete first-to-last; draft save/resume; checklist/validator gating; evidence permissions; revision snapshots; generated documents; VGI deep-links; two founders safe concurrent updates and conflict handling; stage rollbacks and curriculum versioning.

**VGI:** ten exact dimensions, required evidence and missing-data behavior; deterministic scoring; versioned overrides, recalculation tests; history not overwritten; stage/readiness triggers only on approved rubric; public and investor views respect consent.

**Intelligence:** correct recommendations for known gap fixtures; source integrity, document edits/versions; model timeout fallback; prompt injection adversarial tests; scoped retrieval across tenants; human approval of external-facing AI documents; quota controls; no fabricated financial certainty.

**Challenges & knowledge:** moderation workflow; unapproved content inaccessible to public/search; revisions and audit; semantic index tenant filter; bookmarks/downloads; source and copyright metadata, stale content warnings.

**Experts & capital:** double-booking race condition; timezone; messaging authorization; completed-only review; signed payment webhook idempotency and dispute; data room grant expiry/revocation; due diligence access; investor match explainability; no unauthorized fund handling.

**Analytics & CMS:** counts by time window and organization rights; export matches filters; low-count privacy policy; mobile/keyboard/screen readers; PDF generation, CSRF/XSS, upload/attachment security, performance targets and DB indexes.

**Release:** test migration against sanitized copy; rollback dry run; background queues; job retries/dead letter; security audit; backup recovery; multi-environment config and container health; user training records and sign-off.

Generate `docs/owner-alignment/test-matrix.md` with cases, inputs, actual results, test class/URL or screenshot, reviewer/date. Avoid assertions like “100% tested” absent evidence.

## 13. DELIVERABLES CHECKLIST — EVIDENCE REQUIRED

- [ ] Owner-aligned scope matrix and current-state code audit, with missing/partial/implemented statuses
- [ ] Business requirements and stakeholder journey maps, UI/UX wireframes/prototypes and recorded review
- [ ] Approved modular architecture, database ERD, data ownership matrix and tenant/access models
- [ ] Full source code, migrations, policies, seeders, factories and automated tests
- [ ] OpenAPI API reference and IMS/identity integration contracts, sample payloads and error rules
- [ ] Public CMS, admin interface, role-specific dashboards, full nine owner modules with functional flows
- [ ] Six configurable builder modules and 24 steps, versioned publishing, evidence/validation/progress
- [ ] Ten-dimension VGI rubric and versioned scoring/trend/recommendation mechanisms
- [ ] Intelligence recommendations, appropriate AI provider integrations, source provenance and evaluation evidence
- [ ] Moderated Challenge Exchange and Knowledge Hub with access-controlled search/bookmarks/downloads
- [ ] Experts booking/contracts and approved payment adapter; investment diligence/visibility/pipeline and analytics
- [ ] Role-specific training materials, founder/institution/expert/investor/admin manuals and operations runbooks
- [ ] Test plan, unit/integration/E2E/security/accessibility/load test results and UAT sign-offs
- [ ] Docker/cloud deployment and backup/restore/rollback documentation, staging and go-live checklist
- [ ] Knowledge transfer, agreed warranty defect tracking, SLA and post-deployment support plan

**Warranty:** Owner requests warranty/post-deployment support but did **not** specify a fixed number of days. Have owner set duration and scope; do not silently promise a 30-day or 90-day term.

## 14. RECOMMENDED WORK ITEMS / AGENT ASSIGNMENT

| Track | Responsible agent | First deliverables |
|---|---|---|
| Current-state/architecture | PM + senior Laravel architect | repo map, gap matrix, data contract, ERD, decisions |
| Registry/auth/security | backend/security agent | SSO/IMS data boundary, tenant policies, 2FA/audit tests |
| Builder & VGI | domain/backend + product UI agent | schema, six/24 CMS, versioned rubric, end-to-end tests |
| Intelligence | ML/integration + backend QA | rules-first recommendations, context, safe AI adapter/evaluation |
| Challenge/Knowledge | content/CMS + search agent | moderation/versioning, resources, keyword/semantic filtering |
| Marketplace/Capital | backend/integration + UI agent | expert scheduling/contract/payment adapters, investor data rooms/pipeline |
| Dashboards | analytics + frontend agent | cohort/portfolio metrics, startup progress charts, exports |
| Delivery/QA | QA, accessibility, DevOps and documentation | CI, container staging, secure tests, training/runbooks |

Respect current OpenCode agent permissions and project-specific restrictions. Do not globally allow file edits. Delegate narrow tasks, require tests before merge, review migrations and retain changelog.

## 15. PM EXECUTION & PROGRESS REPORT TEMPLATE

For each sprint, create/update `docs/owner-alignment/phase-status.md`:

```text
Date / git branch / commit:
Audit findings (actual code evidence):
Requirement IDs completed / tested / staged / deployed:
Current gap count by critical/high/medium:
Changes made (files/migrations/API/UI):
Security and migration risks:
Automated tests (command, results, failures):
Manual UAT evidence:
Owner decisions required (and provisional safe alternatives):
IMS synchronization and privacy checks:
Next priority tasks with agent and due milestone:
```

**Status definitions:** `implemented` means code exists; `tested` means acceptance evidence passes; `staged` means deployed to QA/staging; `deployed` means observed in production; `owner-approved` means signed acceptance. Never equate these statuses. No empty placeholder screen should be treated as an implemented module.

## 16. FINAL DEFINITION OF DONE

NSP meets owner intent when an authorized startup can register once and use the same canonical identity across IMS/NSP; participate in a structured and usable **six-module, 24-step** Venture Builder; complete a versioned, explainable **ten-dimension** VGI assessment; receive context-relevant guidance along **Assess → Identify Gaps → Guide → Execute → Validate → Improve → Scale**; discover evidence-validated challenges and knowledge; book experts; pursue capital with consent-controlled diligence; and see accurate founder, institution, investor and administrator dashboards. This must work end-to-end with tenant-safe permissions, reliable data integration, auditable operations, API documentation, tests, accessible interfaces and deployable cloud architecture. Official VGI methodology, commercial terms and owner policies must be approved before they are presented as authoritative or used for regulated/real-money workflows.

---

### ADDITIONAL OPENCode OPERATIONAL NOTE

Please place this file in the actual NSP repository root (or `docs/owner-alignment/` with a short root README link) and keep the earlier specification as architectural reference. This revised owner-alignment directive **supersedes any conflicting narrow interpretation of NSP as only a startup marketplace**, but it does not authorize destroying prior functionality. OpenCode PM must use actual codebase evidence to determine implemented vs missing items and deliver the entire revised scope incrementally.
