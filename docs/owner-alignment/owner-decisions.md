# Owner decision register (directive §11)

**Date:** 7 November 2026. Extends `docs/decisions.md` (D1–D13 remain valid). Nothing below is decided by the PM; each row records the **safe provisional implementation** used until the owner decides.

| ID | Decision needed (directive §11) | Provisional implementation now | Risk while open | Needed by | Approver | Status |
|---|---|---|---|---|---|---|
| OD1 | Official titles of the 6 Builder modules and 24 steps, guidance and outputs | Directive's scaffold seeded as a **draft** curriculum, `methodology_status = provisional`; founders see a "Provisional curriculum" notice. Staff can edit/publish without code changes; publish requires exactly 6 modules / 24 steps | Founders work on content the owner may rename; history is preserved across versions | Before founder UAT | Owner + programs lead | Open |
| OD2 | VGI questions, weights, thresholds, stage and readiness mapping, validation authority | Ten owner dimensions enforced on approval; seeded rubric is a provisional draft with equal weights; readiness only computed if a rubric defines it and is labelled provisional; owner sign-off recorded separately (`methodology_status`) | Scores could be read as authoritative — mitigated by labels | Before founder UAT | Owner (D12) | Open |
| OD3 | How an ESO/institution obtains portfolio access and data-sharing consent | Organization requests access with purpose + scopes; startup owner/founder approves; revocable at once; optional expiry | Policy/legal wording not reviewed (D4) | Before institutions onboard | Owner + legal | Open |
| OD4 | AI vendor, hosting/data region, retention, sensitive data, human review | Rules-first guidance needs no vendor; external AI stays behind the existing locked assistant (D13) | None while off | Before enabling AI | Owner + legal (D13) | Open |
| OD5 | Expert payment gateway, currency, fees, commissions, tax, refunds, contract wording, invoices | Sandbox gateway only, ledger + signed webhooks, "Sandbox — no real money" labels; terms acceptance recorded, not a legal e-signature | No real money can move | Before paid bookings | Owner + finance + legal (D6) | Open |
| OD6 | Investor diligence permissions and legal obligations | Founder-controlled per-investor scopes with expiry/revocation; checklists from config; no fund handling | Wording of disclaimers unreviewed | Before investor UAT | Owner + legal (D5) | Open |
| OD7 | Cloud provider, availability targets, SLA/RPO/RTO, storage/search budget | Container definition provided for local/staging; shared-hosting fallback documented as capacity-limited | No scaling claims can be made | Before staging | Owner (D9, D10) | Open |
| OD8 | Official branding and production domains | Configurable brand settings; no Hive branding assumed | — | Before go-live | Owner (D9) | Open |
| OD9 | Semantic search corpus licensing | Keyword search; semantic adapter disabled; licence field per resource; external content linked, not ingested | — | Before semantic search | Owner + legal | Open |
| OD10 | Commercialization rate and outcome definitions | Metric definitions marked provisional with explicit numerator/denominator/time window | Institutions may misread rates | Before institution dashboards are shared externally | Owner + M&E lead | Open |
| OD11 | Training and warranty duration, support coverage | Not promised; manuals written | — | Before contract close | Owner | Open |
| OD12 | IMS/registry operational ownership and migration schedule | Registry inside NSP (M8); IMS integration flag off | — | Before IMS go-live | Owner | Open |
