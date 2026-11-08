# Discovery (Phase 0)

**Date:** 4 November 2026
**Method:** a read-only review of code and SQL files. No production database was queried, and no production system was changed.
**Status words:** *Confirmed* means seen in code, with a file reference. *Assumed* means inferred and needs checking with the owner or against production.

## 1. What was audited

| System | Location | Audited? |
|---|---|---|
| Hive IMS | `D:\projects\ims-site` (live at ims.hivecolab.com) | Yes, code and SQL files only. The local DB login is rejected, so no live schema was read. |
| IMS demo copy | `D:\projects\ims-demo` (ims.kemmytech.com) | No. It is a white-label copy of ims-site and differs only in domain and some demo-only commits. |
| Hive NSP | `D:\projects\nsp-platform` | It was empty before this work. **No NSP code existed before.** |
| EdTech | `D:\projects\edtech-laravel`, `edtech-site` | Not audited. As the spec requires, it is not treated as NSP and its data model is not reused. |
| Shared IdP / registry | — | **None exists** (see 2.2). |

## 2. Hive IMS: confirmed findings

Paths are relative to `ims-site`.

### 2.1 Stack and routing
- Custom PHP, **not Laravel**. It needs PHP 8.1+ (`readonly` properties in `app/Core/LegacyRunner.php`, a `never` return type, and `thecodingmachine/safe` requires `^8.1`).
- Composer packages: phpoffice/phpword, phpoffice/phpspreadsheet, phpmailer 7, dompdf 3.1.
- The root `.htaccess` maps `/x` to `legacy/x.php` and forces HTTPS on `ims.hivecolab.com`. A thin shim (`public/index.php` → `app/Core/ModuleRegistry.php`, about 250 allow-listed routes) runs the legacy scripts.
- Database: MySQL/MariaDB through mysqli (legacy code) and PDO (shim).

### 2.2 Authentication
- Local login with username or email plus password (`password_verify`, rehash on login) (`legacy/includes/login-process.php`).
- **Every login needs a 6-digit code sent by email.** It expires after 10 minutes and allows 5 tries (`login-process.php:372-522`, `verify-process.php`).
- PHP native sessions with httponly, secure, SameSite=Lax cookies. One CSRF token per session, injected into forms and XHR calls (`legacy/includes/security.php`).
- Password reset uses a single-use token. Only its SHA-256 hash is stored, and it expires after 60 minutes (`legacy/includes/password-reset.php`).
- **There is no SSO, OIDC, SAML, JWT login or API-token authentication.** The only OAuth is outbound, to Google Calendar and Drive.

### 2.3 Users and roles
- `users`: `user_id`, `username`, `email`, `password_hash`, `full_name`, `role` (string/ENUM), `phone`, `is_active`, `created_at`, `last_login`.
- **No UNIQUE index on `email` was found** in the January 2026 dump. Duplicate emails are possible.
- There are 20 built-in roles (`legacy/includes/auth.php:15-36`), for example Administrator, Staff, Member, Applicant, Reviewer, Donor/Partner, Program Manager and Program Officer. Code also uses an unlisted `job_seeker` role.
- Custom roles are stored in `ims_roles`, and each maps to a built-in `base_role`. Permissions use `ims_permissions` and `ims_role_permissions`, with a fallback to roles written in the code.

### 2.4 Startups and programmes
- **There is no startups or companies table.** A "startup" is a row in `applications` (`startup_name`, `ursb_registered`, `legal_status`, `business_stage`, team/founder fields, `traction`, `current_revenue`, `funding_sought`, `status`, `submitted_by`).
- Calls for applications are in `application_opportunities`, linked to `cohorts`.
- Review data is in `application_reviews`, `review_criteria`, `review_scores`, `reviewer_assignments`, `shortlisted_applications`, and the AI review tables `ai_application_reviews` and `ai_application_review_scores`.
- After selection: `startup_milestones`, `startup_milestone_evidence`, `startup_field_visits`.
- Hub members are in `members` (with `company_name`). Donor programmes are in `programs`, `projects` and `indicators`.
- **Submitting an application anonymously creates an Applicant account and logs it straight in, without the email code** (`submit-application-process.php:764-810, 891-897`).

### 2.5 APIs and integrations
- **No versioned or external API exists.** About 40 session-authenticated JSON scripts serve the app's own AJAX calls.
- Public embeds: `apply-embed`, `opportunity-embed`, `jobs-embed`.
- Outbound services:
  - email through PHPMailer SMTP;
  - Google Calendar and Drive;
  - OpenAI or Gemini for AI application review;
  - UniFi Wi-Fi;
  - a Power BI embed.
- There is no payment gateway; payments are recorded manually.

### 2.6 Branding
- Branding is stored as rows in `system_settings`: site name, contact details, logo and favicon paths, primary, secondary and sidebar colours, font family, custom font and font scale.
- The values are served through `ims_brand()` (`legacy/includes/site-theme.php`) as CSS variables.

### 2.7 Hosting
- Shared cPanel hosting. Scheduled work runs from cPanel cron scripts (`legacy/cron/`). **There are no queues and no workers.**
- Uploads are stored under `legacy/uploads/` and `public/uploads/`. The timezone is Africa/Kampala.

## 3. Assumed / unknown (must be confirmed)

| # | Item | Why it matters |
|---|---|---|
| U1 | The live `users.role` column type and the full live schema. Most startup tables have no CREATE TABLE in the repo. | Needed to plan alias mapping and the migration dry-run. Get a schema-only dump from production. |
| U2 | Whether the IMS web root is the repo root or `public/`. | Affects where the new `/ims/api/v1` endpoints are mounted. |
| U3 | Whether NSP hosting can run a queue worker (supervisor) or only cron. | Decides how outbox delivery runs (see `deployment.md`). |
| U4 | Whether a cloud IdP (for example Auth0, Entra ID, Zitadel Cloud) or a self-hosted IdP is acceptable. | IMS hosting is shared cPanel, so it cannot run Keycloak. |
| U5 | How many distinct startups exist across `applications.startup_name` and `members.company_name`. | Sizes the duplicate-review work. |

## 4. Consequences for NSP design

1. **There is no IdP to extend.** NSP is built as a standard OIDC client (authorization code + PKCE) behind the `oidc` feature flag. Local login is kept for development and admin until the IdP is chosen (decision D1). IMS will also have to become an OIDC client. That is IMS work, and it needs separate authorization.
2. **There is no registry to extend.** The registry is designed as an API contract (`docs/api/openapi.yaml`). Until hosting is decided (D2), NSP calls it through an adapter interface that reports "unavailable".
3. **IMS has no stable startup ID.** Existing IMS startups need alias mapping (`startup_aliases`) and human duplicate review. **Names alone must never be used to merge.** No registration number is reliably present (`ursb_registered` is a yes/no flag).
4. **IMS role names are free text.** NSP keeps its own platform roles (spec §3.3). IMS reviewer and programme roles stay in IMS. Any mapping is explicit and documented.
5. **IMS has no queue.** Event delivery to and from IMS must work from cron (outbox polling). It cannot rely on a long-running worker.
6. **Security issues found in IMS** (reported to the owner and not fixed here, because IMS changes need authorization):
   - an old default member password is hardcoded in `legacy/cron/reset-default-member-passwords.php` and is in git history;
   - unsigned event check-in QR codes are accepted by default;
   - embed scripts can be framed by any site;
   - applicant accounts skip the email code.
