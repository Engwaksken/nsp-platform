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
| 5: Ecosystem services | **All five modules implemented and tested**: 5a opportunities and events, 5b funding and investors, 5c experts and mentors, 5d talent and jobs (pushed, CI green) and 5e marketplace and startup showcase (tested locally). | Not passed yet: needs the role-based UAT in `testing-uat.md` (Phase 5 script) on staging |
| 6: Communications and intelligence | **All three parts implemented**: 6a notifications and 6b dashboards, KPIs and exports (pushed, CI green); 6c optional AI assistant (tested locally, **switched off until legal review, decision D13**). | Not passed yet: needs the privacy and quality evaluation, including the legal review in `ai-assistant.md` |
| 7: Production readiness | **7a operations** and **7b security review and performance** (pushed, CI green); **7c full-site accessibility sweep and go-live checklist** (done locally). Everything left needs hosting or people: staging, the restore drill on a clean server, penetration test and UAT need hosting (D9, D10). | Not passed: needs a signed go-live checklist |

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

## Phase 5b: funding and investors (implemented 2026-11-08)

Privacy follows decision D5 option (a), the recommended default.

| Area | What exists | Evidence |
|---|---|---|
| Investor verification | Anyone signed in can apply at `/investors/profile`. Staff with **verify-investors** (ecosystem managers, platform admins) verify, decline with a reason, or suspend. Verifying gives the `investor` role; suspending removes it and hides everything at once. | `FundingTest::test_staff_verify_investors_and_the_role_follows` |
| Funding request | One open request per startup: amount, currency, instruments, pitch, use of funds. Owners, founders and admins edit; viewers only read. Closing it hides the startup from investors. | `FundingTest` |
| Listing rule | A startup appears to investors only with an open request **and** the new `investor_listing` consent, and shows only name, sector, location, country, growth stage and the request. Revoking consent hides it immediately. | `test_only_verified_investors_see_startups_and_only_consented_open_requests` |
| Matching | Explainable fit both ways (sector, country, stage, instrument, ticket size), with reasons and "outside their focus". | `FundingTest` |
| Introductions | Founder → investor or investor → startup. Only the other side can accept. Contact details appear only after acceptance. Email notices never contain private data. | `test_introductions_reveal_contacts_only_after_acceptance_and_notes_stay_private` |
| Investor pipeline | Private stage and notes per startup; never shown to the startup, hidden from serialisation. | same test |
| Data room | Private disk (never public URLs). Uploads checked by extension and by content type; 20 MB limit. The team can always download; an investor only with an active grant, which needs an accepted introduction and has an expiry (default 30 days, max 180) and can be revoked. Every download is logged for the startup; investor downloads are also audited. Served as an attachment with `nosniff` and `no-store`. | `test_investor_without_a_data_room_grant_cannot_access_the_pitch_deck`, `test_granted_investor_can_download_until_revoked_or_expired_and_downloads_are_logged`, `test_uploads_are_checked_by_content_not_name` |
| Accessibility | axe audit of 7 funding and investor pages at 1280 and 360 px: 0 violations | `tests/a11y` |

Not done: investor-side document watermarking, deal-room Q&A, and investor reports for Hive (Phase 6 reporting).

## Phase 5c: experts, consultants and mentors (implemented 2026-11-09)

| Area | What exists | Evidence |
|---|---|---|
| Expert verification | Anyone signed in applies at `/experts/profile` (headline, bio, mentor/consultant/both, expertise areas, sectors, languages, availability, optional fee note, capacity). Staff with **manage-experts** verify, decline with a reason or suspend; the `expert` role follows. | `ExpertsTest::test_staff_verify_experts_and_assign_mentors` |
| Directory | Per startup: verified, listed experts only; search, mentor/consultant and expertise filters; reasons ("Knows Agriculture", "Expertise: Finance", "Free sessions"). Average rating shown only from 3 reviews, so no single review is identifiable. | `test_directory_lists_only_verified_listed_experts_with_reasons`, `test_completion_and_one_review…` |
| Requests and mentor matching | Founders (owner, founder, admin) request a consultation or mentoring; only the expert accepts or declines. Staff can assign a mentor directly. One open engagement per startup and expert. | `test_request_accept_and_contacts_are_shared_only_after_acceptance`, `test_unverified_experts_and_consultant_only_mentoring_are_refused` |
| Engagement page | Shared by the expert and the startup team; anyone else gets 404. Contacts appear once active. Sessions (date, length, online with https link, in person or phone), statuses, actions with owner, due date and overdue flag. | `test_sessions_actions_and_note_visibility` |
| Notes visibility | Per session: shared notes (both sides), expert-only notes, startup-only notes. Viewers read but cannot change. Private notes are hidden from serialisation and from the staff overview. | same test |
| Feedback and outcomes | Either side completes with an outcome; the startup gives one rating, "did it help?" and a comment that only the expert and staff see. | `test_completion_and_one_review…` |
| Notifications | Email on request, decision, mentor assignment, session planned, cancellation, completion and feedback. No notes in emails. | `ExpertsTest` |
| Accessibility | axe audit of 8 expert and engagement pages at 1280 and 360 px: 0 violations | `tests/a11y` |

Not done: calendar invites (.ics) and reminders before sessions, paid bookings (decision D6), and expert reporting dashboards (Phase 6).

## Phase 5d: talent and recruitment (implemented 2026-11-10)

| Area | What exists | Evidence |
|---|---|---|
| Vacancies | Startups post full-time, part-time, internship, gig or volunteer roles as drafts, then publish, close, mark filled or move back to draft. Only owners, founders and admins manage them. | `TalentTest` |
| Jobs board | Public `/jobs` with keyword, type, sector and remote filters; only open, unexpired roles of active startups. Shows the startup's name, sector and country (publishing is the startup's choice). | `test_public_board_lists_only_open_vacancies_of_active_startups` |
| Candidate profiles | Headline, summary, skills, work types, location, open to work, CV (PDF or .docx, content-checked, 5 MB, private disk). Hidden by default; opting in to search records the consent time and wording version; opting out clears it at once. Candidates can delete their profile and CV. | `test_search_shows_only_opted_in_candidates…`, `test_cv_upload_checks_content…`, `test_team_members_cannot_apply…` |
| Applications | Explicit consent per application. Each application keeps its own CV copy, so later profile changes do not alter what was sent. Only the startup's owners, founders and admins see applications and CVs; CV downloads are audited. Withdrawing deletes the copy. | `test_apply_shares_a_cv_copy_with_editors_only_and_withdrawal_deletes_it` |
| Pipeline | New → shortlisted → interview → offer → hired, or not selected. Each move is recorded (`hiring_pipeline_events`) and the candidate is emailed with candidate-friendly wording and an optional message. Team notes and interview notes are never shown to the candidate. | `test_pipeline_moves_notify_the_candidate_and_team_notes_stay_private` |
| Interviews | Proposed by the team (online needs an https link); the candidate confirms or asks for another time; the team records held or cancelled. | same test |
| Search and invitations | Editors search opted-in candidates by skill, headline or place; no email or CV is shown. They can invite a candidate to apply to an open vacancy; applying stays the candidate's choice. | `test_search_shows_only_opted_in_candidates…` |
| Retention | `talent:purge-cvs` (daily 02:30) deletes application CV copies `NSP_TALENT_CV_RETENTION_DAYS` (default 180) after the application or vacancy closes. | `test_cv_upload_checks_content_and_retention_purge_deletes_old_copies` |
| Moderation | Staff with **manage-talent** see all vacancies and take one down with a note the startup sees; they cannot see applications or CVs. | `test_viewers_cannot_post_vacancies_and_staff_can_take_them_down` |
| Accessibility | axe audit of 11 jobs, candidate and hiring pages at 1280 and 360 px: 0 violations | `tests/a11y` |

Not done: job alerts by email, structured salary ranges, and hiring reports (Phase 6). The retention period is a proposal until decision D8 sets it.

## Phase 5e: marketplace and startup showcase (implemented 2026-11-11)

| Area | What exists | Evidence |
|---|---|---|
| Startup directory | Public `/startups`, built on the existing `nsp_directory` consent. Only consenting active startups appear, with only the consented fields (name, sector, location, description, website). Revoking removes the startup at once. | `MarketplaceTest::test_directory_shows_only_consented_startups_and_only_consented_fields` |
| Listings | Offers (sell), requests to buy (procurement) and partnership requests, with category, price note, location and an optional end date. Drafts until published; only owners, founders and admins manage them. Public `/marketplace` with keyword, type and category filters. | `test_listings_are_drafts_until_published_and_only_editors_manage_them`, `test_listing_reports_and_expiry` |
| Inquiry messaging | Any signed-in user starts one conversation per listing, for themselves or for a startup they manage. Messages stay on NSP; emails only say there is a new message, never its text, and email addresses are not exchanged. The startup's viewers read, editors reply; either side can close it. | `test_inquiry_conversation_stays_between_the_two_sides_without_email_content` |
| Disputes | Anyone can report a listing; either side can report a conversation, which then becomes visible to staff with **moderate-marketplace** (each view is audited). Staff resolve or dismiss with a note to the reporter and can take the listing down (the startup sees the note) or restore it. | `test_reported_conversation_becomes_a_dispute_staff_can_resolve` |
| Reporting | Admin page with published listings by type, conversations in the last 30 days and open reports. | same test |
| Accessibility | axe audit of 12 marketplace, directory and dispute pages at 1280 and 360 px: 0 violations | `tests/a11y` |

Not done: listing images, seller ratings, payments or escrow (decision D6), a resource directory beyond CMS pages, and fuller marketplace analytics (Phase 6).

## Phase 6a: notifications and email delivery (implemented 2026-11-12)

Channels follow decision D7's recommended default: email and in-app. No SMS or WhatsApp.

| Area | What exists | Evidence |
|---|---|---|
| One notifier | Every activity notice (team join requests, opportunity reminders, funding, experts, talent, marketplace, announcements) goes through `App\Communication\Notifier`. Security and account emails (password reset, sign-in codes, invitations to an address) bypass it and are always sent. | `NotificationsTest::test_module_notices_now_land_in_the_centre`, existing module tests |
| Notification centre | `/notifications` with unread filter, "mark all as read" and an unread count in the menu. Opening a notification marks it read and only follows links on this site. | `test_notices_go_in_app_and_by_email_with_a_delivery_log_and_unsubscribe` |
| Preferences | Per type and per channel (email, on NSP). Defaults: on for activity, **email off for announcements** (newsletter consent is opt-in). | `test_preferences_are_honoured_per_channel` |
| Unsubscribe | Every notifier email carries a signed one-click unsubscribe (`List-Unsubscribe` and `List-Unsubscribe-Post`, RFC 8058) and a link in the text. A GET only asks to confirm, so link scanners cannot unsubscribe people; a POST (from the page or the mail client) does it. | `test_one_click_unsubscribe_needs_a_valid_signature_and_post` |
| Delivery log and retries | `email_deliveries` records queued, sent (from the mailer's sent event) and failed (after 3 attempts with 1, 5 and 15 minute backoff). No message text or addresses are stored. Works with the cron-driven queue. | `test_delivery_log_is_updated_when_sent_and_when_retries_fail` |
| Announcements | Staff with **manage-communications** draft and send to everyone, startup team members, verified investors or verified experts; sent in chunks by a queued job; one send only. | `test_announcements_reach_the_chosen_audience_and_email_only_opted_in_people` |
| Retention | `notifications:prune` (daily 03:00): read notifications after 90 days, all after 365, delivery log after 180. | `test_prune_removes_old_notifications_and_log_rows` |
| Accessibility | axe audit of 6 notification, unsubscribe and admin pages at 1280 and 360 px: 0 violations | `tests/a11y` |

Not done: bounce and complaint processing needs a mail provider webhook (none chosen yet; failures are logged), activity feeds and support tickets (later in Phase 6 or 7).

## Phase 6b: dashboards, KPIs and exports (implemented 2026-11-13)

| Area | What exists | Evidence |
|---|---|---|
| KPI definitions | 13 KPIs defined in `docs/kpi-definitions.md` (registrations, active startups, returning users, onboarding, VGI completion, program participation, opportunity engagement, matches, introductions, funding pipeline, jobs, marketplace). Aggregates only; IMS figures are counts of startups. | `InsightsTest::test_kpis_follow_their_definitions` |
| Staff insights | **Admin › KPIs and reports** for staff with **view-insights** (ecosystem managers, auditors, platform admins): KPIs for 30 days, 90 days or 12 months, plus a six-month table. | `test_insights_are_for_permitted_staff_and_exports_are_audited_and_scoped` |
| Role-aware dashboards | Admin dashboard shows only the queues the role handles (opportunities to review, investors and experts to verify, open reports, failed emails). A startup **Overview** tab links each module's count. Investor, expert and candidate dashboards from Phase 5 complete the set. | `test_admin_dashboard_shows_only_counts_the_role_may_see`, `test_startup_overview_and_data_export_are_team_scoped` |
| Exports | KPI CSV; startup list CSV without contacts, financials or teams (also needs `manage-startups`); applications CSV per vacancy without CVs or team notes (startup editors); the startup's own data as JSON (startup editors). Every export is audited, scoped to what the person may already see, and formula-safe. | `InsightsTest` |
| Accessibility | axe audit of the admin dashboard, insights and startup overview at 1280 and 360 px: 0 violations | `tests/a11y` |

Not done: scheduled emailed reports, charts (tables are used for now), and IMS program aggregates beyond the summaries IMS already sends.

## Phase 6c: optional AI writing assistant (implemented 2026-11-14, switched off)

Full design and review brief: `docs/ai-assistant.md`.

| Area | What exists | Evidence |
|---|---|---|
| Lock | The `ai_assistant` flag can be switched on only when `ANTHROPIC_API_KEY` and `NSP_AI_LEGAL_APPROVED=true` are set; withdrawing approval stops use even with the flag on. | `AssistantTest::test_it_stays_locked_without_key_or_legal_approval` |
| Consent and roles | Per-person, versioned opt-in that can be withdrawn; startup owners, founders and admins only. | `test_consent_and_editor_role_are_required` |
| Data minimisation | Sends only the typed text plus name, sector and stage. Tests prove contact details, financial fields, registration number and the user's email are never sent. Stores counts only, no text. | `test_only_the_typed_text_and_basic_context_are_sent_and_injection_is_contained`, `test_output_is_escaped_labelled_and_never_stored` |
| Prompt-injection defence | Fixed system prompt; founder text inside delimiters with break-out tags stripped; no tools; escaped plain-text output; never auto-saved. | same tests |
| Provenance | "AI-generated" label with model and time; human copies what they use. | `test_output_is_escaped_labelled_and_never_stored` |
| Limits and errors | 20 per person, 50 per startup, 500 platform-wide per day, 6 per minute; refusals and API errors shown plainly; SDK retries. | `test_daily_limits_apply`, `test_refusals_and_errors_are_shown_plainly` |
| API client | Official `anthropic-ai/sdk`; model `claude-opus-5-5`, effort `medium`, server-side refusal fallback (`fallbacks: "default"`). The real client is tested against a local stand-in server for request shape, refusal, 429 and connection failure. | `AnthropicClientTest` |
| Accessibility | axe audit of the consent page, the form and a shown suggestion at 1280 and 360 px: 0 violations | `tests/a11y` |

## Phase 7a: operations (implemented 2026-11-15)

| Item | What exists | Evidence |
|---|---|---|
| Backups (OPS-703) | `nsp:backup` (daily 01:30): database (`VACUUM INTO` for SQLite; `mysqldump --single-transaction` with credentials in a private option file for MySQL), private files and media, and a SHA-256 manifest, all **AES-256 encrypted**. Production refuses unencrypted backups. Each backup is checked after writing, and the oldest beyond `NSP_BACKUP_KEEP` are deleted. `--verify-only` checks any archive. | `OperationsTest` (encryption of every entry, wrong password, tampering, rotation); a real run against the local database |
| Restore | Manual, documented procedure (no command can overwrite live data) and a restore-drill log. | `docs/operations-runbook.md` §4 |
| Health | Admin › System health, `nsp:health` (exit code for cron monitors) and token-protected `GET /health` (404 without a token; statuses only, 503 on failure). Checks: settings, database, scheduler heartbeat, queue age, failed jobs, IMS deliveries, email, backup age and encryption, disk. | `OperationsTest` |
| Runbook (DOC-709 part) | Schedules, monitoring set-up, off-server copies, secrets that are not in backups (`APP_KEY`, backup password), what to do per failing check, incident steps, routine checks. | `docs/operations-runbook.md` |
| CI | The zip extension is added so backup tests run on GitHub too. | `.github/workflows/tests.yml` |

## Phase 7b: internal security review and performance (implemented 2026-11-15)

| Item | What was done | Evidence |
|---|---|---|
| Security review (SEC-705 part 1) | An independent read-only review of every route and module: no critical issues and no cross-tenant access. 3 medium and 8 low findings fixed (Host header and reset links, two-factor for everyone who enabled it, investor contact exposure, password-spraying limits, security alert emails, verified SSO emails only, exact notification link check, IMS connect needs edit rights, live-content deletion, AI delimiter, re-review on identity changes). | `docs/security-review-2026-11.md`, `SecurityReviewFixesTest` and updated tests |
| N+1 guard (PERF-708) | Lazy loading now throws in tests and is logged elsewhere. A query-count test loads 17 list pages with 3 and then 15 rows: queries must not grow with rows. It found and fixed one N+1 (growth stage per startup on the investor dashboard and the funding page). | `QueryCountTest` |
| Indexes | Composite indexes for consent lookups by purpose, sign-ins by user and time (KPIs), assessments by startup and status, and KPI date ranges. Rollback tested. | `2026_11_15_080000_add_performance_indexes.php` |

Still needed on staging: an external penetration test, and a load test on a low-bandwidth profile (PERF-708 acceptance).

## Phase 7c: accessibility sweep and go-live checklist (2026-11-15)

| Item | What exists | Evidence |
|---|---|---|
| Full-site sweep (A11Y-706) | `nsp:audit-fixture` (local and testing only) creates one realistic record in every module, lists 81 pages, and removes only its own marked data with `--clean`. `tests/a11y/audit.js` checks every page at 1280 and 360 px: axe WCAG 2.1 A/AA, status, silent redirects, overflow, one h1, lang, title, the 404 page, and keyboard focus visibility. | Result: 163 checks, **0 violations, 0 problems**; `AuditFixtureTest`; `docs/accessibility.md` |
| Manual accessibility plan | Screen reader (NVDA, TalkBack or VoiceOver), zoom, keyboard-only UAT, errors, contrast and time limits, with a results table. No compliance claim before it is done. | `docs/accessibility.md` §2 |
| Go-live checklist (REL-710) | Decisions, environment, security, data protection, backups and monitoring, quality, people and content, launch, sign-off. | `docs/go-live-checklist.md` |

## Test results (local)
- `php artisan test`: **224 passed, 1537 assertions** (7b: 222 and 1520; 7a: 212 and 1452; 6c: 206 and 1417; 6b: 198 and 1345; 6a: 193 and 1285; 5e: 186 and 1239; 5d: 181 and 1158; 5c: 174 and 1050; 5b: 168 and 956; 5a: 158 and 855; end of Phase 4: 149 and 796).
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
