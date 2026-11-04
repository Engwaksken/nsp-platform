# Phase status report

**Date:** 4 November 2026
**Status words used here:** *Implemented* means the code exists in this repository. *Tested* means automated tests pass locally (PHP 8.2.12, SQLite in memory). *Staged* and *Deployed* mean released to staging or production; **nothing is staged or deployed yet.**

## Summary

| Phase | Status | Gate |
|---|---|---|
| 0: Discovery and architecture | Documents written. Owner decisions D1 to D11 are pending (`decisions.md`). | Not passed: needs owner approval of the architecture |
| 1: Foundation and CMS | Implemented and tested locally | CMS and admin permissions pass tests. Not staged. |
| 2 to 7 | Not started. Planned in `backlog.md`. | — |

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

## Test results (local)
- `php artisan test`: **90 passed, 411 assertions** (Phase 1 baseline was 70 and 311).
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
