# Data ownership

**Date:** 4 November 2026
**Status:** Proposed contract for owner approval. The registry does not exist yet. Visibility defaults and consent rules depend on decisions D4 and D5 (`decisions.md`) and on legal review.

## 1. Ownership matrix (spec §4)

| Domain | Canonical owner | Examples | Rules |
|---|---|---|---|
| User identity | Hive IdP | subject ID, verified email, authentication state, MFA | Passwords are never copied between apps. Apps store the `(issuer, subject)` pair only. |
| Shared startup profile | Hive Registry | name, founders, sector, location, founded year, description, website, team size, revenue band, traction, funding raised, contact | Registry is authoritative. Each field has provenance and an access policy. |
| IMS programme data | IMS | applications, eligibility, answers, scores, reviewer comments, decisions, cohorts, attendance, milestones, programme documents | Never sent to NSP unless a scoped use case is approved and documented. |
| NSP growth data | NSP | VGI, growth stage, investor readiness, capital needs, expert and talent needs, marketplace, matches, progress history | NSP is authoritative. Investor disclosure needs founder consent. |
| NSP CMS content | NSP | pages, posts, media, menus, FAQs, testimonials, partners | NSP only. Not shared. |
| Platform roles | Each app | NSP platform roles; IMS reviewer and programme roles | Roles are not synchronised. Any mapping is explicit and documented. |

## 2. Shared startup profile: per-field rules

Legend:
- **Visibility:** `private` = startup members and authorised Hive staff only. `ecosystem` = signed-in users with a role that needs it. `public` = anyone.
- **Sensitivity:** L = low, M = medium (business-sensitive), H = high (personal data or financial).
- **Edit:** who may change the current value in the registry.
- **Consent:** whether explicit consent is needed before the field is shared outside the startup and Hive staff.

| Field | Owner | Default visibility | Sensitivity | Who may edit | Consent needed |
|---|---|---|---|---|---|
| startup_uuid | Registry | ecosystem | L | Nobody (system-generated) | No |
| legal_name | Registry | ecosystem | L | owner, founder, admin | No |
| display_name | Registry | public (when profile is published) | L | owner, founder, admin | No (founder chooses to publish) |
| registration_number | Registry | private | M | owner, admin; verified by Hive staff | Yes |
| country | Registry | public | L | owner, founder, admin | No |
| year_founded | Registry | public | L | owner, founder, admin | No |
| description | Registry | public | L | owner, founder, admin | No |
| website | Registry | public | L | owner, founder, admin | No |
| sector_id, sub_sector_id | Registry | public | L | owner, founder, admin | No |
| headquarters_location | Registry | ecosystem | M | owner, founder, admin | No |
| team_size | Registry | ecosystem | M | owner, founder, admin | Yes for investors |
| revenue_band | Registry | private | H | owner, founder | Yes |
| traction_summary | Registry | private | H | owner, founder, admin | Yes |
| funding_raised_amount, funding_currency | Registry | private | H | owner, founder | Yes |
| contact_email | Registry | private | H (personal data) | owner, admin | Yes |
| contact_phone | Registry | private | H (personal data) | owner, admin | Yes |
| founders: name, title | Registry | ecosystem | M (personal data) | the founder, owner | Yes, from each founder |
| founders: ownership disclosure | Registry | private | H | the founder, owner | Yes, from each founder |
| memberships | Registry | private | M | owner, admin (invite/revoke) | No |
| profile_completion | Registry (computed) | private | L | System | No |

Hive staff roles (`platform_admin`, `ecosystem_manager`, `support`) may read private fields only for a documented purpose. Each such read of an H field is audit-logged. `auditor` reads audit logs, not profile values.

Defaults follow spec §4: revenue, funding, traction and personal founder and contact details are private by default.

## 3. App-specific copies

| Copy | Where | Rule |
|---|---|---|
| Application-time snapshot | IMS | Frozen at submission. Later registry changes do not alter it. Changes go through an audited IMS amendment workflow. |
| Membership cache | NSP (`startup_membership_cache`, planned) | Not authoritative. Refreshed from registry events and on login. Used for fast authorisation checks, re-checked against the registry for sensitive actions. |
| Display cache of public fields | NSP or IMS (optional) | Public fields only. Refreshed by `registry.startup.updated.v1`. |

NSP stores `startup_uuid` on every startup-related record. It does not copy H fields into its own tables.

## 4. Conflict rules

1. **Registry wins** for canonical shared fields. IMS and NSP do not keep their own editable copy.
2. **Optimistic concurrency.** Every write sends `If-Match` with the current ETag (version). A stale version gets `412 Precondition Failed`. The client must reload and show the user both values. No silent last-write-wins.
3. **Field-level permission.** The registry rejects writes to fields the caller may not edit (`403` with the field list).
4. **Provenance.** Each accepted change writes `startup_field_provenance` (source system, user subject, version, consent reference).
5. **Snapshots are immutable.** A registry change never edits a submitted IMS application.
6. **Concurrent cross-system edits** that both arrive with the same base version: the first wins, the second gets 412. If a sync job (not a user) hits a conflict, it goes to a conflict-review queue for a human.
7. **NSP-owned attributes** (growth stage, VGI) are written only by NSP. The registry does not store them.
8. **Consent revocation** stops future disclosure at once. The registry emits `registry.consent.revoked.v1`. Consumers delete or hide cached copies of the affected fields. Audit records are kept as the retention policy requires (D8).

## 5. Open questions
- Exact consent purposes and wording (D4).
- What investors may see before and after a data-room grant (D5).
- Whether `display_name` is public by default or only after the founder publishes.
- Whether Hive staff need explicit purpose selection before viewing H fields.
