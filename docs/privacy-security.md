# Privacy and security

**Date:** 4 November 2026
**Status:** Phase 1 controls are in the Phase 1 build scope; `phase-status.md` records what is implemented and locally tested. Nothing is deployed. No penetration test or legal review has been done. This document does **not** claim compliance with any law or standard.

## 1. Threat model summary

Assets, from most to least sensitive:
1. Founder personal data and private startup data (revenue, funding, traction, contact). Not stored in NSP in Phase 1.
2. Admin and editor accounts (can change the public site).
3. Contact form messages (names, emails, message text).
4. Public site content and media.

| Threat | Example | Main controls |
|---|---|---|
| Account takeover | Password guessing, stolen session | Rate-limited login, hashed passwords, session regeneration, secure cookies, login audit; MFA via IdP (planned) |
| Privilege misuse | Editor changes roles | spatie roles and permissions, policies on every admin action, audit log |
| Injection / XSS | Script in a page block or post body | Eloquent bindings, Blade escaping, allow-listed HTML in rich text, CSP |
| CSRF | Forged admin form post | Laravel CSRF middleware on all session forms |
| Malicious upload | Script uploaded as image | MIME and extension allow-list, size limit, random file names, no execution in storage path |
| Spam and abuse | Contact form flooding | Honeypot, rate limit, consent checkbox |
| Open redirect | `?redirect=` to external site | Relative-path redirects only; SEO redirects are admin-managed |
| Token forgery (OIDC) | Forged ID token | Full validation checklist (`oidc-sso.md` section 4) |
| Cross-tenant access (later) | Founder edits another startup's UUID in URL | Policies on `startup_uuid` membership, tests per spec §12 (Phase 2) |
| Webhook replay (later) | Replayed `ims.application.accepted.v1` | HMAC, timestamp window, idempotent inbox (`ims-integration.md`) |
| Data leakage from IMS (later) | Reviewer comments reach NSP | Field allow-list in IMS API, contract tests |
| Secrets leakage | `.env` committed | `.env` git-ignored; secrets only in environment |

## 2. Controls

| Control | Phase 1 scope (local, not deployed) | Planned |
|---|---|---|
| HTTPS | App generates HTTPS URLs when configured | Force HTTPS and HSTS at hosting level (Phase 7) |
| Security headers | Middleware sets CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy | Tighten CSP (nonces), report-only monitoring |
| CSRF | Laravel middleware | — |
| Authentication | Local login with rate limit; OIDC client behind `oidc` flag | Shared IdP (D1), MFA for admins |
| Authorization | spatie/laravel-permission, 11 platform roles, policies | Startup membership policies (Phase 2) |
| Audit | `audit_logs` for admin changes, `login_audits` for logins, IPs stored as hashes | Audit of reads of sensitive fields (Phase 2+) |
| Input validation | Form requests | API schema validation (Phase 3) |
| File uploads | Allow-listed types, size limit, stored with random names | Malware scanning where hosting allows; signed expiring URLs for private files |
| Contact form | Honeypot, rate limit, consent checkbox with consent text version | Ticket routing, retention purge job |
| Feature flags | Unbuilt modules are off and shown as unavailable | — |
| Encryption at rest | Not needed yet (no H-sensitivity data in Phase 1) | Laravel encrypted casts for H fields, encrypted backups |
| Logging | Laravel logs; no passwords or tokens logged | PII redaction, central error monitoring |
| Backups | None (not deployed) | Daily DB backups, restore drill (Phase 7) |
| Dependency security | Composer lock file | `composer audit` in CI |

## 3. Uganda Data Protection and Privacy Act, 2019

The Act and its 2021 Regulations are **review input only**. A qualified lawyer or privacy officer must review NSP before launch. The points below are questions for that review, not legal advice.

- **Registration.** Does Hive need to register as a data collector, processor or controller with the Personal Data Protection Office (PDPO)?
- **Lawful basis and consent.** Which processing relies on consent, and which on contract or legitimate purpose? Consent wording (D4) must be approved.
- **Purpose limitation and minimisation.** Each field shared between IMS, NSP and the registry needs a stated purpose.
- **Data subject rights.** Access, correction, objection and deletion requests need a process and owner.
- **Security safeguards.** Controls in section 2 should be mapped against the Act's security duty.
- **Breach notification.** The Act requires notifying the PDPO of breaches. Timelines and procedure must be confirmed (section 5).
- **Cross-border transfers.** A cloud IdP or hosting outside Uganda transfers personal data abroad. This must be checked (D1).
- **Children.** Talent and internship features may reach people under 18. Rules for minors need review before Phase 5.
- **Data protection officer.** Confirm whether one must be designated.

## 4. Retention (placeholders, owner decision D8)

No retention period below is approved. Values are `TBD` until the owner decides, after legal review.

| Data | Proposed default for discussion | Status |
|---|---|---|
| Contact messages | TBD (for example 24 months after closing) | Pending D8 |
| `login_audits` | TBD (for example 12 months) | Pending D8 |
| `audit_logs` | TBD (for example 7 years for admin changes) | Pending D8 |
| Page revisions | TBD (for example last 50 per page) | Pending D8 |
| Soft-deleted pages and posts | TBD (for example purge after 90 days) | Pending D8 |
| Deactivated user accounts | TBD | Pending D8 |
| Revoked consents (records) | Keep as audit evidence; period TBD | Pending D8 |
| Integration inbox/outbox rows | TBD (for example 90 days after delivery) | Pending D8 |

Purge jobs will run from the scheduler once periods are approved.

## 5. Incident response outline

1. **Detect and report.** Anyone who suspects an incident tells the named security contact (to be named by the owner).
2. **Triage.** Decide severity: personal data involved, systems affected, still ongoing.
3. **Contain.** Disable affected accounts or features (feature flags), rotate secrets and OIDC client secrets, block IPs if needed.
4. **Preserve evidence.** Copy logs, `audit_logs` and `login_audits` before any cleanup.
5. **Assess notification duty.** With legal advice, decide whether to notify the PDPO and affected people, and by when.
6. **Recover.** Fix the cause, restore from backup if needed, confirm integrity.
7. **Review.** Write a short post-incident report with actions and owners within two weeks.

Owner, contacts and on-call arrangements are open items. They will be recorded in `operations-runbook.md`.

## 6. Known issues outside NSP

`discovery.md` section 4 lists IMS security issues (hardcoded default password in git history, unsigned QR check-in, framable embeds, applicant accounts skipping the email code). They are reported to the owner. They are not fixed here because IMS changes need authorization.
