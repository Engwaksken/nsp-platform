# Hive NSP: Startup Growth & Ecosystem Platform

Laravel 12 application for Hive NSP. Hive IMS stays the system for programme applications; NSP covers the wider startup growth journey. The two will share one Hive login (OIDC) and one startup registry. Neither exists yet: see `docs/decisions.md`.

**Status:** Phase 0 (discovery and architecture) documents and Phase 1 (foundation and CMS) are implemented and tested locally. Nothing is deployed. See `docs/phase-status.md`.

## Quick start (local)

Requirements: PHP 8.2+ and Composer. No Node.js is needed.

```
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed          # SQLite by default; roles, permissions and draft placeholder pages
php artisan storage:link
php artisan nsp:create-admin you@example.org   # asks for a password
php artisan serve
```

Open http://127.0.0.1:8000 for the site and http://127.0.0.1:8000/admin for the admin area.

## Tests

```
php artisan test          # 70 tests; no network or database server needed
vendor/bin/pint --test    # code style
```

The OIDC tests sign tokens with a test-only key in `tests/Fixtures/`. Never use that key anywhere else.

## Where things are

| Path | What |
|---|---|
| `app/Http/Controllers/Site` | Public website |
| `app/Http/Controllers/Admin` | Admin CMS |
| `app/Http/Controllers/Auth` | Local login and OIDC single sign-on |
| `app/Services/Oidc/OidcClient.php` | OIDC relying party (authorization code + PKCE, JWKS validation) |
| `app/Contracts/StartupRegistry.php` | Registry adapter. A disabled implementation is used until the registry exists. |
| `app/Support` | Settings, feature flags, audit logging, Markdown, page blocks, contrast checks |
| `config/nsp.php` | Feature flag defaults and OIDC settings |
| `public/css/nsp.css`, `public/js/nsp.js` | All styling and scripts (no build step) |
| `docs/` | Discovery, architecture, ERD, API draft, decisions, backlog, deployment, manuals |

## Documentation

Start with `docs/phase-status.md`, then `docs/decisions.md` (owner decisions needed) and `docs/architecture.md`.
