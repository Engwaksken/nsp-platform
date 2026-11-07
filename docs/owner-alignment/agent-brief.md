# Shared brief for module agents (owner alignment, Nov 2026)

Read this before writing code. The owner directive is `NSP_Owner_Requirements_Alignment_OpenCode_PM_Implementation.md` (repo root); your module section is named in your task.

## The codebase (do not restart or rewrite it)
- Laravel 12, PHP 8.2, SQLite for tests, Blade views, **no Node build step**: styling is `public/css/nsp.css`, scripts `public/js/nsp.js`. Reuse existing classes (`container section`, `panel`, `card`, `grid`, `btn btn-primary|btn-secondary btn-sm`, `badge badge-on|badge-draft|badge-scheduled`, `table-wrap`, `tags`/`tag`, `meta`, `hint`, `empty`, `alert alert-warning`, `visually-hidden`). CSP forbids inline scripts and inline event handlers. Look at `resources/views/workspace/growth/*.blade.php` and `resources/views/admin/vgi/*` for patterns.
- Public/workspace pages extend `layouts.public` (`@extends('layouts.public', ['pageTitle' => '...'])`, `@section('content')`); workspace pages `@push('head')<meta name="robots" content="noindex">@endpush` and include `@include('workspace.startups.nav')` + `@include('partials.flash')`. Admin pages extend `layouts.admin` with `@section('title')`.
- Startup tenancy: canonical `startups` table keyed by `uuid` (`App\Models\Registry\Startup`). Membership roles owner/founder/admin/team_member/viewer (`App\Models\Registry\StartupMembership`, `canEdit()` = owner/founder/admin). Workspace controllers `use ResolvesMembership;` and call `$this->membershipOr404($request, $startup)` — non-members get **404**, never 403. Every venture-bound row carries `startup_uuid` (FK to `startups.uuid`, cascade).
- Never duplicate the canonical startup profile. Read it from `Startup` / its profile.
- Audit sensitive actions with `App\Support\Audit::log('module.action', $model, [...])` (never log secrets/private values).
- Feature flags: `App\Support\Features::enabled('key')`; route middleware `feature:key`. Your flag already exists in `config/nsp.php`. Put module-specific config in a **new** `config/<module>.php` file — do not edit `config/nsp.php`.
- Permissions already added to `database/seeders/RolesAndPermissionsSeeder.php` (manage-organizations, manage-builder-curricula, publish-builder-curricula, review-builder-steps, manage-challenges, review-challenges, manage-knowledge, publish-knowledge, manage-intelligence, manage-payments; role `builder_reviewer`). Check with `$user->can('...')` / `can:` middleware. Super admin passes all checks. Do not edit the seeder; if you truly need another permission, say so in your report.
- Admin routes: prefix `admin`, name `admin.`, middleware `['auth', 'admin', 'mfa']` plus `can:` and `feature:`.
- Tests: `tests/Feature/<Module>/...Test.php` extending `Tests\TestCase` (RefreshDatabase, seeds roles, admin MFA pre-verified). Make users with `User::factory()->withRole('startup_user')->create()`; create startups with `app(App\Registry\RegistryService::class)->createStartup($owner, [...], [])` and add members via `invite()`/`acceptInvitation()` (see `tests/Feature/Growth/GrowthTest.php`).
- Mail: use existing patterns (`App\Communication\Notifier`) if you notify; never put private data in emails.
- Files: private disk only for evidence/contracts/data-room style documents; validate by content type (see `Workspace\FundingController::upload`).

## Rules for working in parallel (several agents share this working tree)
1. **Only create/edit files in your own module.** Your routes go in `routes/modules/<your-file>.php` (auto-loaded by `routes/web.php`). Your migration uses the timestamp prefix you were given. Your views live under your own view folders. Do not edit shared files (`routes/web.php`, `config/nsp.php`, seeders other than your own, layouts, nav partials, other modules' code) unless your task explicitly allows it. If you need a change in a shared file, describe it in your report instead.
2. Route names that the shared navigation already looks for are listed in your task — use them exactly.
3. Keep the tree bootable at all times: write each PHP file complete. If a test fails because of another module's half-written file, wait a moment and re-run; do not edit their file.
4. Run your own tests: `php artisan test tests/Feature/<Module>` (and `--filter`). Run `vendor/bin/pint <your paths>` before finishing. At the end run the **full** suite once (`php artisan test`, ~90 s) and report the exact summary line.
5. **Do not commit, branch, stash, or reset.** The PM commits.
6. Honesty rules from the owner directive: label provisional methodology as provisional; never claim AI training, payment settlement, integrations or deployments that don't exist; no real customer data; no secrets.
7. Write a short module doc `docs/owner-alignment/modules/<module>.md`: what exists (files, routes, tables), acceptance criteria → test names, what is provisional, owner decisions needed, known gaps.

## Report back (final message)
- Files created/changed (paths), migrations and tables, routes (names), permissions used.
- Test commands run and their exact result lines (your module + full suite).
- Acceptance criteria from the directive: met / partly / not met, with the test that proves each.
- Shared-file changes you need the PM to make.
