# Owner decision log

**Date:** 4 November 2026
Spec §2 lists decisions that need owner approval before irreversible work. Until each is approved, NSP uses adapters and feature flags and does not hardcode a guess.

## 1. Pending decisions

| ID | Decision | Options | Recommendation | Impact if delayed | Status |
|---|---|---|---|---|---|
| D1 | Identity provider choice and hosting | (a) Managed cloud IdP (Auth0, Entra External ID, Zitadel Cloud, Okta). (b) Self-hosted Keycloak or Zitadel on a VPS. (c) Laravel-built IdP. | (a), after checking cost per user and data residency. (b) only if Hive has a VPS and staff to run it. Not (c). See `oidc-sso.md` section 8. | Blocks Phase 2 SSO and IMS OIDC work. NSP stays on local login. | Deferred by owner (2026-11-05): keep local login with admin two-factor until an IdP is chosen |
| D2 | Registry hosting and operational owner | (a) Separate registry app. (b) Isolated module inside NSP. (c) Isolated module inside IMS. | (b) at first, with the full API contract, then extract to (a) when hosting allows. IMS is custom PHP on shared hosting, so (c) is harder. Owner must name who runs it. | Blocks Phase 2 registry, Phase 3 integration. | **Decided 2026-11-05: (b) inside NSP** (see M8) |
| D3 | Merge and migration policy | (a) Staff review all candidate groups. (b) Auto-link on strong evidence only (same submitting user, registration number), staff review the rest. | (b), with names never used as deciding evidence and owner confirmation for ambiguous cases. | Blocks legacy migration dry-run apply step. | Pending |
| D4 | Registration and opt-in consent language | Draft by Hive, reviewed by legal | Legal review of purposes and wording before Phase 2 launch. Version each text. | Blocks consent screens and cross-app sharing. | Pending |
| D5 | Investor visibility and data-sharing rules | (a) Investors see public fields only until a founder grants access. (b) Verified investors see ecosystem fields by default. | (a). Data room access only by explicit per-investor grant. | Blocks Phase 5 investor module. | Pending |
| D6 | Payment gateways and paid services | (a) None. (b) Mobile money aggregator (for example Flutterwave, Pesapal). (c) Card gateway. | (a) until a paid service is defined. IMS records payments manually today. | Only affects paid marketplace or event features. | Pending |
| D7 | Communications channels | Email only; email + SMS; email + WhatsApp Business; in-app | Email and in-app first. Add SMS or WhatsApp only with consent and a cost owner. | Affects Phase 6 notifications. | Pending |
| D8 | Data retention periods | Per data type (see `privacy-security.md` section 4) | Owner sets periods after legal review. | Purge jobs cannot be built; data accumulates. | Pending |
| D9 | Production domains | For example `nsp.hivecolab.com`, `id.hivecolab.com`, `registry.hivecolab.com` | Subdomains of the existing Hive domain, so cookies stay separate per app. | Blocks OIDC redirect URI registration and TLS. | Pending |
| D10 | Hosting and queue capacity | (a) Shared cPanel with cron only. (b) VPS with supervisor workers and Redis. | (a) works with the cron outbox design. (b) if Phase 5-6 email volume or matching jobs grow. Confirm discovery U3. | Affects delivery delays and job design. | Pending |
| D11 | Laravel 13 upgrade timing | (a) Upgrade once hosting has PHP 8.3+. (b) Stay on Laravel 12 until its security support ends. | (a), before Phase 7 production readiness, on a branch with full tests. | Running an older major longer; upgrade cost grows with code size. | Pending |

## 2. Decisions made

| ID | Date | Decision | Reason | Follow-up |
|---|---|---|---|---|
| M1 | 2026-11-04 | Build NSP on Laravel 12 with PHP 8.2 instead of Laravel 13 / PHP 8.3+ | Local PHP is 8.2 and hosting is believed to be 8.2 | Upgrade path in `architecture.md` section 8; timing is D11 |
| M2 | 2026-11-04 | NSP is a standard OIDC client (authorization code + PKCE) behind the `oidc` flag, with local email/password login kept for development and admin until a shared IdP is chosen | No IdP exists (discovery 2.2) | Restrict local login once D1 is live |
| M3 | 2026-11-04 | OIDC users are matched by `(issuer, subject)`. Linking to an existing local account by email is off by default (`OIDC_LINK_VERIFIED_EMAIL=false`) | Email is not a safe identifier; IMS has no unique email index | Owner may enable after D1 if the IdP verifies email |
| M4 | 2026-11-04 | Phase 1 scope: foundation, RBAC (spatie/laravel-permission), public CMS, branding settings, feature flags, audit logs, security headers. Later modules show as unavailable behind flags. | Registry and IdP are not decided | Phases 2-7 in `backlog.md` |
| M5 | 2026-11-04 | `StartupRegistry` adapter interface with a "disabled" implementation | Registry hosting undecided (D2) | Real HTTP adapter in Phase 2 |
| M6 | 2026-11-04 | Platform roles: super_admin, platform_admin, ecosystem_manager, investor, expert, consultant, recruiter, startup_user, content_editor, support, auditor | Spec §3.3 | IMS reviewer and programme roles stay in IMS |
| M7 | 2026-11-04 | Local git only; no remote repository, CI or deployment yet | Owner instruction | Choose remote and CI before Phase 2 |
| M8 | 2026-11-05 | The Hive Startup Registry is built as an isolated module inside NSP (own tables, `RegistryService`, `StartupRegistry` interface), not a separate app | Owner chose option (b) of D2; works on current hosting | Expose the Registry API v1 to IMS in Phase 3; extract to its own service later behind the same contract |
| M9 | 2026-11-05 | Startup duplicates: same registration number or website blocks a second record; same name only warns and queues staff review; merges need a reviewer, written evidence and are reversible | Spec §5 Journey C; IMS has no reliable registration numbers | Revisit with D3 merge policy |
