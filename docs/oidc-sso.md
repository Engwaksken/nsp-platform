# OIDC single sign-on

**Date:** 4 November 2026
**Status:** The NSP OIDC client is Phase 1 scope, behind the `oidc` feature flag, off by default (config in `config/nsp.php`; build state in `phase-status.md`). **No shared IdP exists.** IMS has no SSO (`discovery.md` 2.2). Nothing here has been tested against a real IdP or deployed.

## 1. Model

- The IdP owns credentials, MFA, password reset, email verification and IdP sessions.
- NSP and IMS are separate OIDC relying parties (clients). Each keeps its own local session.
- An app creates a local session only after it has validated the ID token.
- Local email/password login stays in NSP for development and admin access until D1 is decided. It is controlled by `NSP_LOCAL_LOGIN`.

## 2. Login flow (authorization code + PKCE)

Route paths in the diagram are indicative.

```mermaid
sequenceDiagram
    actor U as User
    participant NSP
    participant IdP
    U->>NSP: GET /auth/oidc/redirect
    NSP->>NSP: Create state, nonce, code_verifier; store in session
    NSP-->>U: 302 to IdP /authorize (code_challenge S256, state, nonce, scope)
    U->>IdP: Sign in (+ MFA if required)
    IdP-->>U: 302 to OIDC_REDIRECT_URI?code&state
    U->>NSP: GET /auth/oidc/callback
    NSP->>NSP: Check state matches session (single use)
    NSP->>IdP: POST /token (code, code_verifier, client auth)
    IdP-->>NSP: id_token, access_token
    NSP->>IdP: GET JWKS (cached, refetch on unknown kid)
    NSP->>NSP: Validate ID token (checklist in section 4)
    NSP->>NSP: Find user by (iss, sub) or apply linking policy
    NSP->>NSP: Regenerate session id, log login_audits
    NSP-->>U: 302 to intended page
```

## 3. Required IdP features

| Feature | Required | Why |
|---|---|---|
| OIDC discovery (`/.well-known/openid-configuration`) | Yes | Endpoint and JWKS lookup |
| Authorization code flow with PKCE (S256) | Yes | Spec §3.3 |
| Signed ID tokens (RS256 or ES256) and JWKS with key rotation | Yes | Signature check |
| `email` and `email_verified` claims | Yes | Linking policy, contact |
| RP-initiated logout (`end_session_endpoint`) | Yes | Central logout |
| MFA, enforceable per group or role | Yes | Spec §10 for admins |
| Password reset, email verification, account recovery | Yes | IdP owns credentials |
| Client credentials grant | Yes, for the registry API | Server-to-server calls |
| Custom scopes / audience for the registry API | Yes | Least privilege |
| Back-channel logout | Nice to have | Ends sessions in all apps |
| Self-service registration with custom fields and consent | Nice to have | Journey A and B |
| Data residency and export | Owner decision | Privacy review (D1, D8) |

## 4. ID token validation checklist

NSP rejects the login if any check fails. Each failure is logged in `login_audits` with a reason code, not the token.

- [ ] `state` matches the value stored in the session, and is used once.
- [ ] Code exchange uses the stored `code_verifier`.
- [ ] Signature verifies against a JWKS key matching `kid`. `alg` is on an allow-list. `none` and HMAC algorithms are refused.
- [ ] `iss` equals `OIDC_ISSUER` exactly.
- [ ] `aud` contains `OIDC_CLIENT_ID`.
- [ ] If `aud` has more than one value, `azp` is present and equals `OIDC_CLIENT_ID`.
- [ ] `exp` is in the future; `iat` is not in the future (allow 60 seconds clock skew).
- [ ] `nonce` equals the stored nonce.
- [ ] `sub` is present.
- [ ] Redirect URI is fixed in config; NSP never takes it from the request.
- [ ] The post-login redirect target is a relative path on NSP (no open redirect).

## 5. Logout (RP-initiated)

1. User clicks Sign out in NSP.
2. NSP logs the event, invalidates the local session and regenerates the CSRF token.
3. If the user signed in by OIDC, NSP redirects to the IdP `end_session_endpoint` with `id_token_hint`, `post_logout_redirect_uri` = `OIDC_POST_LOGOUT_REDIRECT_URI`, and `state`.
4. The IdP ends its session and returns the user to NSP.

Limits: without back-channel logout, an IMS session stays open after NSP logout until it expires or the user logs out of IMS. This must be documented for users and tested when IMS joins.

## 6. Account linking policy

- Users are matched by the pair `(oidc_issuer, oidc_subject)`. This pair is unique in `users`.
- Email is not an identifier. It can change and IMS has no unique email index.
- `OIDC_LINK_VERIFIED_EMAIL=false` (default): if no user has the `(iss, sub)` pair, NSP creates a new user. If an existing local account already uses that email, login is refused with a message to contact support. An admin then links the accounts by hand after checking identity. This is audit-logged.
- `OIDC_LINK_VERIFIED_EMAIL=true`: NSP links to an existing local account only if `email_verified` is `true`, exactly one local user has that email, and that user has no OIDC link yet. Each automatic link is audit-logged. Enable only if the IdP is trusted to verify email ownership.
- An OIDC-only user has `password = NULL` and cannot use local login.
- New OIDC users get the `startup_user` role only. Privileged roles are granted in NSP by an admin.

## 7. Configuration

| Key | Example | Meaning |
|---|---|---|
| `NSP_OIDC_ENABLED` | `false` | Default for the `oidc` feature flag. An admin can override it in `feature_flags`. |
| `OIDC_ISSUER` | `https://id.example.org` | Exact `iss` value; discovery is fetched from it |
| `OIDC_CLIENT_ID` | `nsp-web` | Client ID registered at the IdP |
| `OIDC_CLIENT_SECRET` | (secret) | Confidential client secret. Never committed. |
| `OIDC_REDIRECT_URI` | `https://nsp.example.org/auth/oidc/callback` | Must match IdP registration |
| `OIDC_SCOPES` | `openid profile email` | Requested scopes |
| `OIDC_POST_LOGOUT_REDIRECT_URI` | `https://nsp.example.org/` | Return URL after IdP logout |
| `OIDC_LINK_VERIFIED_EMAIL` | `false` | Linking policy (section 6); `config('nsp.oidc.link_verified_email')` |
| `NSP_LOCAL_LOGIN` | `true` | Default for the `local_login` feature flag (local email/password login). Plan to restrict it to admins once the IdP is live. |

Domains in examples are placeholders. Production domains are decision D9.

## 8. IdP options for a cPanel-hosted IMS

IMS runs on shared cPanel. It cannot host a Java or Go IdP. The IdP must therefore be a managed service or run on a separate server.

| Option | Pros | Cons |
|---|---|---|
| Managed cloud IdP (for example Auth0, Microsoft Entra External ID, Zitadel Cloud, Okta) | No servers to run; MFA, recovery and patching included; high availability | Recurring cost per active user; data stored with a third party, often outside Uganda (needs transfer review); vendor lock-in; custom branding limits on cheaper tiers |
| Self-hosted Keycloak on a VPS | Free software; full control; mature; all features above | Needs a VPS (Java, about 2 GB RAM or more), a database, TLS, backups, upgrades and monitoring; Hive must have someone to run it; outage stops all logins |
| Self-hosted Zitadel on a VPS | Lighter than Keycloak (Go); good multi-tenant model | Same ops burden as Keycloak; smaller community; needs PostgreSQL or CockroachDB |
| Laravel app as IdP (for example Laravel Passport plus an OIDC add-on) | Same stack as NSP; could run on PHP hosting | Not a full OIDC provider out of the box; MFA, recovery, discovery, logout and key rotation must be built and secured; spec warns against custom SSO tokens. Not recommended. |

Recommendation for D1: a managed cloud IdP, unless the owner already has a VPS and staff to run Keycloak. Check data residency and cost first.

## 9. What IMS must change to become an OIDC client

All of this is IMS work. It needs separate authorization and is not started.

1. Add an OIDC client library (for example `jumbojett/openid-connect-php`) or a small vetted implementation, with the checks in section 4.
2. Add `oidc_issuer` and `oidc_subject` columns to IMS `users` with a unique pair index.
3. Add a callback route to `ModuleRegistry` (new routes 404 otherwise).
4. Decide how the current email code (6-digit login code) relates to IdP MFA. With OIDC, IdP MFA should replace it.
5. Plan migration of existing IMS users: invite each user to the IdP, then link by `(iss, sub)` after they sign in. Do not link by email alone, because IMS emails are not unique.
6. Change anonymous application submission, which today auto-creates and logs in an Applicant account without the email code. It should go through IdP sign-up.
7. Add RP-initiated logout and, if supported, back-channel logout.
8. Keep IMS reviewer and programme roles in IMS. Map them from local data, not from IdP claims, unless agreed.
