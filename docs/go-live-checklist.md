# Go-live checklist (REL-710)

Hive NSP goes live only when every **required** line is ticked and the owner signs at the bottom. For each line, record who checked it, when, and where the evidence is (a link, screenshot or document).

Status on 2026-11-15:
- **Done** means built and tested in the code (CI is green on every push).
- **Open** needs a person, a decision or hosting.

## 1. Owner decisions (`decisions.md`)
| ✓ | Item | Required | Status |
|---|---|---|---|
| ☐ | D3 merge and migration policy (duplicates, IMS startups import) | Yes | Open |
| ☐ | D4 registration and consent wording reviewed by legal | Yes | Open |
| ☐ | D8 data retention periods approved; `NSP_TALENT_CV_RETENTION_DAYS` and notification periods set to match | Yes | Open |
| ☐ | D9 production domain chosen; DNS and TLS certificate ready | Yes | Open |
| ☐ | D10 hosting chosen (shared cPanel with cron, or a VPS) | Yes | Open |
| ☐ | D12 growth rubric reviewed with the programs team and approved by a second admin | Yes, before founders see Growth | Open |
| ☐ | D13 AI assistant: legal review done (it stays locked until then) | No (stays off) | Open |
| ☐ | D1 identity provider; D6 payments; D11 Laravel 13 | No (deferred) | Deferred |

## 2. Environment (`deployment.md`)
| ✓ | Item | Required |
|---|---|---|
| ☐ | Code deployed from a tagged commit with green CI | Yes |
| ☐ | `.env` set:<br>• `APP_ENV=production`<br>• `APP_DEBUG=false`<br>• `APP_URL=https://<domain>`<br>• `NSP_MFA_ENFORCE=true`<br>• `MAIL_*` set for a real SMTP provider | Yes |
| ☐ | The web server answers only for the NSP domain (no catch-all virtual host) | Yes |
| ☐ | `php artisan migrate --force` and `php artisan db:seed --class=RolesAndPermissionsSeeder --force` run; caches built | Yes |
| ☐ | Cron entry for `schedule:run` every minute; Admin › System health shows the scheduler OK | Yes |
| ☐ | Feature flags reviewed: only approved modules are on (Admin › Feature flags) | Yes |
| ☐ | First super admin created with `php artisan nsp:create-admin`; two-factor sign-in set up at once | Yes |
| ☐ | SPF, DKIM and DMARC records for the sending domain; a test email lands in the inbox, not spam | Yes |

## 3. Security
| ✓ | Item | Required | Status |
|---|---|---|---|
| ☑ | Internal code review with fixes (`security-review-2026-11.md`) | Yes | Done |
| ☐ | External penetration test on staging; high and critical findings fixed and retested | Yes | Open |
| ☐ | `composer audit` clean on the deployed code | Yes | CI checks every push |
| ☐ | Response headers checked on the live domain: CSP, HSTS, `X-Content-Type-Options` and `Referrer-Policy` | Yes | Open |
| ☐ | Admin accounts reviewed: least privilege, every one with two-factor sign-in | Yes | Open |
| ☐ | Secrets stored in the password manager: `APP_KEY`, `NSP_BACKUP_PASSWORD`, DB and SMTP passwords, API client secrets | Yes | Open |

## 4. Data protection
| ✓ | Item | Required |
|---|---|---|
| ☐ | Privacy notice published and linked in the footer; it covers registry sharing, investors, experts, talent, marketplace and notifications | Yes |
| ☐ | Registration with Uganda's Personal Data Protection Office (and other countries served, as advised) | Yes |
| ☐ | Data processing agreements with the hosting, email and (if enabled) AI providers | Yes |
| ☐ | A named data protection lead, and the breach procedure in `operations-runbook.md` §6 agreed | Yes |

## 5. Backups and monitoring (`operations-runbook.md`)
| ✓ | Item | Required | Status |
|---|---|---|---|
| ☑ | Encrypted, verified nightly backups and health checks built and tested | Yes | Done |
| ☐ | `NSP_BACKUP_PASSWORD` set; first backup made; daily off-server copy working | Yes | Open |
| ☐ | **Restore drill** on a clean server: steps 1 to 9 pass; time recorded | Yes | Open |
| ☐ | Uptime monitor on `/health` with `NSP_HEALTH_TOKEN`; alerts reach two people | Yes | Open |

## 6. Quality
| ✓ | Item | Required | Status |
|---|---|---|---|
| ☑ | Automated tests (222+) and code style pass in CI | Yes | Done |
| ☑ | Automated accessibility sweep: 81 pages at 1280 and 360 px, 0 violations (`accessibility.md`) | Yes | Done |
| ☐ | Manual accessibility checks in `accessibility.md` §2 (screen reader, zoom, keyboard) | Yes | Open |
| ☐ | Load test on staging with a low-bandwidth profile; agreed response times met (PERF-708) | Yes | Open |
| ☐ | UAT scripts in `testing-uat.md` (Phase 1 and Phase 5) passed on staging, with screenshots | Yes | Open |
| ☐ | IMS ↔ NSP end-to-end run on staging with real IMS data (Phase 3 gate) | If IMS integration is on | Open |

## 7. People and content
| ✓ | Item | Required |
|---|---|---|
| ☐ | Admin training on `admin-user-manual.md`: verification, moderation, disputes, announcements and the KPIs | Yes |
| ☐ | Founder guide (`startup-user-manual.md`) published as help pages | Yes |
| ☐ | Home page, About, FAQs, contact routing and partner logos final | Yes |
| ☐ | Launch announcement drafted (Admin › Announcements); support contact staffed for the first two weeks | Yes |

## 8. Launch and stabilisation
| ✓ | Item | Required |
|---|---|---|
| ☐ | Rollback plan rehearsed: previous release folder plus database dump (`deployment.md`, Updates) | Yes |
| ☐ | Launch window agreed, outside programme deadlines | Yes |
| ☐ | Daily health review for 14 days after launch; issues logged and triaged | Yes |

## Sign-off
| Role | Name | Signature | Date |
|---|---|---|---|
| Owner (Hive) | | | |
| Technical lead | | | |
| Data protection lead | | | |
