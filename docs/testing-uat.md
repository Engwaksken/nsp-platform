# Testing and UAT

Run all automated tests with `php artisan test`. They use SQLite in memory and need no network: the identity provider is faked with `Http::fake`, and ID tokens are signed with a **test-only** RSA key in `tests/Fixtures/`, which must never be used anywhere else.

## Test matrix

Status: **A** = automated test passes locally. **M** = manual check done. **—** = not done yet. **P** = planned for a later phase.

| Spec §12 requirement | Phase | Test | Status |
|---|---|---|---|
| Admin role/permissions, CRUD, publishing | 1 | `AdminPermissionsTest`, `PageRevisionsTest` | A |
| Content publishing incl. scheduling, drafts hidden | 1 | `PublicSiteTest` | A |
| Pagination, exports | 1 | Pagination is used on every list. Exports are not built. | Partly |
| Notification preferences | 6 | — | P |
| Responsive/mobile and accessibility flows | 1 | `tests/a11y/audit.js`: axe WCAG 2.1 A/AA on 25 pages at 1280 and 360 px with 0 violations and no sideways scroll; skip link checked by keyboard. No manual screen-reader test yet. | A (automated part) |
| Admin two-factor sign-in (MFA) | 1 | `MfaTest`, `TotpTest` | A |
| Password reset | 1 | `PasswordResetTest` | A |
| OIDC: PKCE, state, nonce, signature, aud/iss/exp, alg confusion | 1/2 | `OidcLoginTest` | A |
| User matched by IdP subject, not email | 1/2 | `OidcLoginTest::test_returning_user_is_matched_by_subject_not_email` | A |
| IMS-first founder enters NSP without re-signup | 2-3 | Needs IdP and IMS changes | P |
| NSP-first founder applies to IMS with prefill and snapshot | 3 | NSP side: `ConnectFlowTest`. IMS side: see ims-site. End-to-end run: see phase-status.md | A (NSP) |
| Two founders, one startup, no duplicate | 2 | `StartupRegistryTest::test_two_founders_join_one_startup_by_invitation_without_duplicates` | A |
| Founder of two startups cannot cross-access | 2 | `StartupRegistryTest::test_founder_of_two_startups_cannot_open_a_third_by_changing_the_url` | A |
| IMS migration, alias mapping, duplicate review, rollback | 2/3 | Duplicate review, reversible merge and aliases: `DuplicateReviewTest`. IMS import dry-run is Phase 3 | Partly |
| Consent revocation stops disclosure | 2/3 | NSP refuses the API and connect codes after revocation and delivers `registry.consent.revoked.v1` to IMS (`ConnectFlowTest`, `OutboxDeliveryTest`) | A (NSP) |
| Registry or IMS down → retries and reconcile | 2/3 | Outgoing retries, dead letters and replay: `OutboxDeliveryTest`. | The workspace shows a message when the registry throws (code path exists, no test yet) | P |
| Concurrent edit → version conflict | 2 | `StartupRegistryTest::test_concurrent_edits_are_refused_instead_of_overwriting` | A |
| Duplicate or replayed webhook rejected | 3 | `InboundEventsTest::test_duplicates_are_acknowledged_once_and_tampering_or_replays_rejected` | A |
| IMS reviewer notes never exposed | 3 | Field whitelist drops scores, comments and answers: `InboundEventsTest` | A |
| Marketplace messages hidden from staff until reported | 5 | `MarketplaceTest::test_inquiry_conversation_stays_between_the_two_sides_without_email_content` | A |
| Unsubscribe link cannot be triggered by a GET or a forged signature | 6 | `NotificationsTest::test_one_click_unsubscribe_needs_a_valid_signature_and_post` | A |
| Exports scoped and audited; no contacts in staff startup list | 6 | `InsightsTest` | A |
| AI assistant sends no confidential fields; injection contained | 6 | `AssistantTest` | A |
| Security review fixes (Host header, MFA for all, spraying limits, SSO verified email) | 7 | `SecurityReviewFixesTest`, `MfaTest`, `OidcLoginTest` | A |
| List pages do not grow queries with rows (N+1) | 7 | `QueryCountTest` | A |
| Investor blocked from data room without grant | 5 | `FundingTest::test_investor_without_a_data_room_grant_cannot_access_the_pitch_deck` (also expiry, revocation, unverified investors) | A |
| Deterministic VGI scoring | 4 | — | P |

Extra Phase 1 security tests: Markdown/HTML escaping, unsafe link schemes, a script disguised as an image, open-redirect protection after login, rate limits, the contact-form honeypot, and security headers on 404s.

## Accessibility audit (optional, needs Node.js and Chrome)
```
cd tests/a11y && npm install
NSP_MFA_ENFORCE=false php artisan serve     # in the project root, local machine only
node audit.js http://127.0.0.1:8000 <admin-email> <admin-password>
```
Set `CHROME_PATH` if Chrome is not in the default Windows location. Never turn off `NSP_MFA_ENFORCE` on staging or production.

## UAT script for Phase 1 (staging)
Run each step as the role named and record pass or fail with a screenshot.

1. **Content editor** creates a page with a hero and text section and saves it as a draft. The public address returns "not found". The preview shows the page.
2. **Content editor** cannot choose "Published" or edit a live page. The message explains why.
3. **Ecosystem manager** publishes the page with a publish time 10 minutes ahead. It appears only after that time.
4. **Ecosystem manager** edits the page twice, then restores the first revision. The page returns to draft with the old content.
5. **Content editor** uploads a JPG, then a `.php` file renamed `.png`. The second is refused.
6. **Platform admin** sets brand colours. A colour with low contrast shows a warning, and the site uses the new colours.
7. **Platform admin** routes "Partnership" to a team email. A test message arrives at that inbox, and the admin inbox shows it.
8. **Support** opens the message and closes it. The audit log shows who read it.
9. **Platform admin** tries to give someone super_admin and is refused. A super admin can do it.
10. **Startup user** signs in and sees the workspace with "Not available yet" on each service. Opening `/admin` returns 403.
11. On a phone (360 px wide), the menu opens and closes, and forms are usable.
12. Keyboard only: the skip link works, focus is visible, and every admin form can be submitted.
13. **New admin** signs in with a password and is sent to set up two-factor sign-in. They scan the QR code, save the recovery codes, and then the admin area opens.
14. **Admin** signs out and back in. After the password, the 6-digit code is required. A wrong code is refused, and a recovery code works once.
15. **Super admin** resets two-factor sign-in for another admin who "lost their phone". That admin must set it up again.
16. **Any local user** uses "Forgot your password?", receives the email, sets a new password, and finds that the old sessions are signed out.

## UAT script for Phase 5 (staging, role-based)
Use separate accounts for each role. Pass = every step behaves as written.

1. **Founder (owner)** registers a startup, turns on the directory consent and finds it under **Startups**; turns it off and it disappears.
2. **Founder** saves an opportunity, registers interest, and sees a recommendation with its reasons.
3. **Investor** applies; **ecosystem manager** verifies them. **Founder** opens a funding request with investor sharing on; the investor sees it and expresses interest; the founder accepts; contacts appear on both sides.
4. **Founder** uploads a pitch deck. Before sharing, the investor cannot open it (not found). After **Share data room** with an end date the investor downloads it and the founder sees the download in the log. After **Remove access** it is not found again.
5. **Expert** applies; **ecosystem manager** verifies them and assigns them as a mentor to the founder's startup. Both are emailed. They plan a session and write shared and private notes; each side sees only the right notes; staff see no notes. The founder completes and gives feedback.
6. **Founder** posts a vacancy and publishes it; it appears under **Jobs**. **Candidate** creates a profile with a CV, applies with consent, is shortlisted with a message, confirms an interview, then withdraws; the CV copy is gone.
7. **Founder** searches candidates: only opted-in people appear, without email or CV, and can be invited.
8. **Founder** publishes a marketplace offer. **Another user** sends a message; the founder replies; a team **viewer** can read but not reply; staff cannot open it. The buyer reports it; staff read it, resolve with a note and take the listing down; the founder sees the note.
9. **Content editor** cannot open the investor, expert, vacancy or marketplace admin pages (403).
10. Every page above works on a 360 px phone and by keyboard alone.
