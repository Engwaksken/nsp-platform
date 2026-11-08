# Internal security review, November 2026 (SEC-705, part 1)

An independent, read-only code review of the whole application, done before staging. It is **not** a penetration test: an external test against staging is still required (SEC-705, part 2).

**Result:** no critical findings and no way to read another startup's data. Every nested route binding checks its parent startup. Three medium and several low findings were fixed and covered by tests (`SecurityReviewFixesTest`, plus additions to `MfaTest`, `OidcLoginTest`, `ConnectFlowTest` and `FundingTest`).

| # | Severity | Finding | Fix |
|---|---|---|---|
| 1 | Medium | Password-reset links took their host from the request's Host header. If the web server forwarded any host, an attacker could get a reset link pointing to their own site. | `trustHosts()` rejects other hosts outside local and testing, and production forces the root URL to `APP_URL` (with https). |
| 2 | Medium | Two-factor sign-in that founders, investors and experts turned on was never asked for. Only admins were challenged. | Anyone with two-factor sign-in on is challenged at sign-in. |
| 3 | Medium | After an accepted introduction, investors saw the company contact email and phone, which the startup's visibility settings do not share with investors. | Investors see only the owners', founders' and admins' emails. |
| 4 | Low/Medium | Sign-in limits were per email and IP only, which allowed password spraying from one IP. | Extra limits: 30 failures per IP in 10 minutes, and 20 failures per account in 15 minutes from any IP. |
| 5 | Low | An admin who has not yet set up two-factor sign-in can set it up with the password alone. | The account owner is now emailed whenever two-factor sign-in is turned on, turned off, reset, or gets new recovery codes. Remaining risk: set up two-factor sign-in when you create an admin account (see `admin-user-manual.md`). |
| 6 | Low | Single sign-on created accounts for email addresses the identity provider had not verified. | Accounts are created only for verified email addresses. |
| 7 | Low | Notification links were checked with a text prefix, so a lookalike host passed. | Exact scheme, host and port match. |
| 8 | Low | Any team member, including viewers, could connect the startup to IMS. | Only owners, founders and admins can. |
| 9 | Low | Content editors could delete published pages and posts. | Deleting live content needs publish rights. |
| 10 | Low | The AI delimiter could be rebuilt from nested tags. Impact was limited (no tools, escaped output). | Tags are removed repeatedly until none remain, whatever the case or spacing. |
| 11 | Low | A verified investor or expert could change their organisation, website or headline and stay verified. | Changing these sends the profile back for review. |

**Accepted as designed:**
- Team viewers may update growth-action status.
- The Registry API serves startups whose sharing consent is on.
- Signed IMS events are trusted, because only registered API clients can send them.

**Checked and found sound:**
- Tenant scoping and parent checks on every nested binding.
- Mass assignment and the hidden private fields (investor, expert, startup and team notes; CV paths).
- XSS: escaping, Markdown and URL rules.
- Upload checks, and private downloads with `nosniff`.
- OIDC: state, nonce, PKCE, issuer, audience and expiry.
- TOTP replay protection, and hashed recovery codes.
- Password reset: throttled, with the same answer whether or not the account exists.
- The signed unsubscribe link (the only CSRF exception).
- API HMAC, replay window and idempotency.
- CSP and HSTS.
- Health token comparison.
- Backup encryption, and the private credentials file used during backups.

**Before go-live (deployment):**
- `APP_DEBUG=false`
- `APP_URL` set to the https domain
- the web server answers only for the NSP domain
- `NSP_MFA_ENFORCE=true`
- an external penetration test on staging
