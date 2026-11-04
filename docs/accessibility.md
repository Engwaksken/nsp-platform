# Accessibility record (A11Y-706)

Target: WCAG 2.1 level AA. **No compliance claim is made** until the manual checks below are done and recorded.

## 1. Automated sweep
**How to run** (local machine, about 10 minutes; Node.js and Chrome needed):
```
php artisan nsp:audit-fixture --password=<pw>          # sample data in every module; lists the pages in tests/a11y/pages.json
NSP_MFA_ENFORCE=false php artisan serve
cd tests/a11y && npm install && node audit.js http://127.0.0.1:8000 admin@a11y.nsp.test <pw>
php artisan nsp:audit-fixture --clean                  # removes every record it made
```

**What it checks on every page, at 1280 px and 360 px:**
- axe-core WCAG 2.0 and 2.1 A/AA rules
- HTTP status, with no silent redirects
- horizontal overflow
- exactly one `h1`, a `lang` attribute and a `<title>`
- the 404 page

**Keyboard:** the first 15 Tab stops on the home page and the admin dashboard. The first stop must be "Skip to main content", and every stop must show a visible focus outline.

**Result, 2026-11-15:**
- 16 public and 65 signed-in pages (163 page-width combinations including the 404 page).
- **0 axe violations, 0 pages with problems.**
- Keyboard: first stop "Skip to main content"; 0 stops without visible focus.

Automated tools find only part of WCAG failures (roughly a third to a half). The manual checks below are required.

## 2. Manual checks (to do on staging)
Record pass or fail, the tester and the date. Use real devices where possible.

| # | Check | Pages | Result | Tester / date |
|---|---|---|---|---|
| 1 | Screen reader on Windows (NVDA + Firefox or Chrome): register a startup, invite a team member, apply for a job, send a marketplace message | Public site, workspace | | |
| 2 | Screen reader on a phone (TalkBack on Android, or VoiceOver on iPhone): sign in, open notifications, save an opportunity | Public site, workspace | | |
| 3 | Zoom to 200% and 400% (reflow): nothing is cut off, and no horizontal scrolling except data tables | All forms, tables, admin | | |
| 4 | Keyboard only: every action in the UAT scripts, including confirm dialogs, file uploads and the menu | All | | |
| 5 | Error messages: wrong form input is announced and linked to the field; the flash messages are read | Forms | | |
| 6 | Colour: brand colours set in Admin › Settings keep 4.5:1 contrast (the settings page warns, so check the live theme) | All | | |
| 7 | Time limits: the two-factor 5-minute window and session expiry warn or allow a retry | Sign-in | | |
| 8 | Language: plain English, with no unexplained jargon, on founder-facing pages | Workspace | | |
| 9 | Documents: PDFs uploaded by Hive (policies, guides) are tagged and readable | Media library | | |

## 3. Known limits
- Data tables scroll sideways on small screens inside a labelled, focusable region. This is the accepted pattern.
- Content written by startups (descriptions, listings, vacancies) and their uploaded documents are outside Hive's control. Guidance for authors is in the founder guide.
