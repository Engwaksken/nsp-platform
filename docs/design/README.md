# Hive NSP design files

| File | What it is | Open with |
|---|---|---|
| `docs/diagrams/nsp-system-flows.drawio` | System flowcharts: context, sign-in, founder, investor, organizations, admin, IMS integration, deployment (one tab per flow) | [app.diagrams.net](https://app.diagrams.net) → File → Open from → Device, or the draw.io desktop app / VS Code extension |
| `docs/diagrams/nsp-erd.drawio` | ER diagram: an overview of the 12 modules, then one tab per module with every table, column, primary key (PK), foreign key (FK, with the table it points to) and unique key (UQ) | diagrams.net, as above |
| `docs/diagrams/nsp-erd.dbml` | The same schema as text (136 tables, 262 relationships) | Paste into [dbdiagram.io](https://dbdiagram.io) for an automatic full-schema diagram; it can also export SQL, PDF and PNG |
| `docs/design/nsp-figma-tokens.json` | Design tokens from `public/css/nsp.css`: colours, fonts, radii, shadows and spacing, with Light and Dark themes | Figma plugin **Tokens Studio for Figma** → Load from file |

The ER diagram and tokens are generated from the code, so they match the live system. Regenerate them after schema or style changes (scripts: ask the developer; they read `information_schema` from a migrated database and the `:root` blocks of `nsp.css`).

## Getting the UI into Figma

Figma files (`.fig`) cannot be written by tools outside Figma, so the screens are brought in from the running site, which gives **editable Figma layers** (frames, text, auto layout), not pictures:

1. In Figma, install the plugin **html.to.design** (by divRIOTS) and its Chrome extension.
2. Public pages: in the plugin, paste a URL (list below), choose desktop 1440 and mobile 390 widths, and import.
3. Signed-in pages (member area, admin): open the page in Chrome while signed in, click the html.to.design extension, and send the page to Figma.
4. Import the tokens: Tokens Studio → Load from file → `nsp-figma-tokens.json`, then **Create styles / variables** so colours and spacing become Figma variables with Light and Dark modes.
5. Recommended Figma file structure: pages **Cover**, **Foundations** (tokens, type, icons: Font Awesome 6 Free Solid), **Components** (buttons, fields, badges, cards, collapsible card, tabs, table, modal, sidebar, top bar), **Public**, **Member area**, **Investor**, **Admin**, **Flows** (prototype links between frames).

Use test data (the audit fixture or a staging copy), not real startups' private details, when importing screens.

## Key screens to import

**Public:** `/` home · `/opportunities` and one opportunity · `/directory` and one startup · `/jobs` and one job · `/marketplace` · `/knowledge` · `/challenges` · `/news` · `/contact` · `/login` · `/forgot-password`

**Member area:** `/workspace` dashboard (Quick actions and Services tabs) · `/account` (Personal details, Password, Appearance, More settings) · `/notifications` · startup profile `/workspace/startups/{id}` (Company, Private details, Team) · edit/register startup (3 tabs) · Team · Data sharing (collapsible cards) · Organizations · Growth · Venture Builder · Funding · Capital Exchange (founder) · Talent · Marketplace listings · My challenges · Knowledge saved

**Investor and expert:** `/investors` dashboard · investor startup page · `/investors/pipeline` (Capital Exchange) · diligence · `/experts` dashboard · engagement

**Admin:** `/admin` dashboard · Branding and settings (5 tabs) · Feature flags · Users and roles · Organizations (list, detail, popups) · Announcements and email log (tabs, popups) · Opportunities (form popup with tabs) · Startups · Audit log

Capture the main states too: empty, filled, validation errors, popup open, dark theme, and phone width.
