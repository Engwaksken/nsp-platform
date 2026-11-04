# KPI definitions

The KPIs on **Admin › KPIs and reports** (`App\Analytics\Kpis`). Every figure is a count or a share. None shows names, contact details or individual records. The period is the last 30 days, 90 days or 12 months, ending now.

| KPI | Definition | Source |
|---|---|---|
| New accounts | Accounts created in the period. | `users.created_at` |
| New startups | Startup profiles created in the period. Merged duplicates are excluded. | `startups` |
| Active startups | Active startups where at least one active team member signed in successfully during the period. Shown against the total of active profiles. | `login_audits` and `startup_memberships` |
| Returning users | Of people who signed in during the previous period of the same length, the share who signed in again during this period. Shows "n/a" when nobody signed in before. | `login_audits` |
| Profiles at least 80% complete | Share of active startups whose profile completion is 80% or more. A snapshot, not limited to the period. | `startup_profiles.profile_completion` |
| Startups with a growth assessment | Share of active startups with at least one submitted assessment (snapshot). The detail shows assessments submitted in the period. | `vgi_assessments` |
| Startups enrolled in Hive programs | Distinct startups that IMS reported as enrolled. The detail shows distinct startups whose applications were submitted in the period. IMS sends summaries only, and they are counted as startups, never shown as applications. | `startup_program_records` |
| Opportunity saves and registrations | Bookmarks plus registrations of interest created in the period. The detail shows opportunities open now. | `opportunity_bookmarks`, `opportunity_interests` |
| New matches | Investor introductions plus expert or mentor engagements started in the period, from either side or assigned by staff. | `investor_introductions`, `expert_engagements` |
| Introductions accepted | Investor introductions accepted in the period. | `investor_introductions.decided_at` |
| Open funding requests | Funding requests open now. The detail gives the total sought per currency. | `funding_requests` |
| Vacancies published | Vacancies first published in the period. The detail gives applications received and hires recorded in the period. | `vacancies`, `job_applications`, `hiring_pipeline_events` |
| Marketplace conversations | Marketplace conversations started in the period. The detail gives the listings published now. | `marketplace_inquiries`, `marketplace_listings` |

**Monthly table:** new accounts and new startups for each of the last six calendar months.

## Exports

| Export | Who | Contents | Audit action |
|---|---|---|---|
| KPIs (CSV) | Staff with `view-insights` (ecosystem managers, auditors, platform admins) | The figures above for the chosen period | `insights.kpis_exported` |
| Startup list (CSV) | Also needs `manage-startups` | UUID, names, registration number, country, year founded, sector, location, profile %, status and date registered. **No** contacts, financials or team members. | `insights.startups_exported` |
| Applications for a vacancy (CSV) | That startup's owners, founders and admins | Applied date, name, email, headline, stage and whether the person was invited. **No** CVs or team notes. | `talent.applications_exported` |
| Opportunity registrations (CSV) | Staff with `manage-opportunities` | Names, emails, startup, status and message | `opportunity.registrations_exported` |
| Our startup's data (JSON) | That startup's owners, founders and admins | What the startup entered: profile, team, consents, growth, funding requests, data-room file list, vacancies, listings and program summaries. **No** other people's private data: no CVs, notes or messages. | `workspace.data_exported` |

Every CSV starts cells that begin with `=`, `+`, `-` or `@` with an apostrophe, so spreadsheets do not run them as formulas.
