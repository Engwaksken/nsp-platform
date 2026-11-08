# Admin guide: Hive NSP website

Sign in at `/login`. Staff with an admin role land on the **Dashboard**. The menu shows only what your role allows.

## Two-factor sign-in (required for every admin role)
- The first time you sign in, you are asked to set up an authenticator app (Google Authenticator, Microsoft Authenticator or Authy). Scan the QR code and enter the 6-digit code.
- **Save the 8 recovery codes** somewhere safe. Each one works once if you lose your phone.
- After that, every sign-in asks for your password and then the code from the app.
- Lost your phone and your codes? Ask a super admin to reset two-factor sign-in on your user page. They should confirm who you are first, for example by phone.
- Forgot your password? Use **Forgot your password?** on the sign-in page. People who sign in with their Hive account reset their password there instead.

## Who can do what

| Role | Can do |
|---|---|
| Content editor | Write pages and news as drafts, upload media, edit menus, FAQs, testimonials and partners |
| Ecosystem manager | Everything a content editor can, plus publish, redirects, contact messages, startups, opportunities, verifying investors and experts, assigning mentors, moderating vacancies handling marketplace disputes, and sending announcements |
| Support | Read and handle contact messages |
| Auditor | Read the audit log and the KPIs |
| Platform admin | Everything except granting super admin or platform admin |
| Super admin | Everything |

## Pages
1. **Pages › New page.** Enter a title. The address (slug) fills in for you, and you can change it.
2. **Add sections:** choose a type, then **Add section**. Use **Move up** or **Move down** to reorder.
   - **Hero:** the big banner at the top, with an optional button.
   - **Text:** headings and paragraphs in Markdown, such as `**bold**`, `## Heading` and `[link](https://…)`.
   - **Impact numbers:** one per line, as `120 | Startups supported`. Only publish numbers you can back up.
   - **Call to action**, **Testimonials**, **Partners**, **FAQs** and **Latest news** show content managed elsewhere.
3. **Status:**
   - **Draft** is hidden from the public.
   - **Published** is visible. If the publish date is in the future, the page waits until then; this is "scheduled".
   - **Archived** is hidden but kept.
4. **Preview** shows the page before it goes live.
5. **Revisions** lists every save. **Restore** copies an old version back as a draft, so check it before publishing again.

The home page is the page with the address `home`. It can be unpublished, but not deleted.

## News
News works like pages, with one article body. Add categories under **News › Categories**.

## Media library
- Upload JPG, PNG, WebP, GIF, ICO or PDF files up to 5 MB.
- Add a short description of what an image shows, for screen readers. Leave it empty for decorative images.
- Compress large photos before uploading; many visitors use mobile data.

## Menus
Each menu has links to pages or to custom addresses (`/news` or `https://…`). Lower order numbers show first.

## Testimonials
A testimonial appears only when **"The person agreed in writing…"** is ticked and **Show on the website** is ticked. Keep the written consent on file.

## Redirects
When a page moves, add a redirect from the old address to the new one. A redirect only applies when nothing exists at the old address.

## Contact messages
- Each topic can be sent to a team inbox (**Branding and settings › Contact form routing**). Every message also stays in **Contact messages**.
- Opening a message is recorded in the audit log, because it contains personal data.
- Mark spam as **Spam**. Close messages once you've answered them.

## Branding and settings
- Set the site name, logo, favicon, colours and font.
- Under each colour, the contrast with white is shown. Primary and secondary colours need at least 4.5:1, or white text on buttons will be hard to read.
- Logos and favicons must first be uploaded in the media library.

## Users and roles
- Add staff, tick their roles, and set the account to active.
- Leave the password empty for people who will sign in with Hive single sign-on.
- You can't deactivate yourself or remove your own admin access.

## Feature flags
Modules that are not built yet show "Not built yet" and can't be switched on. Single sign-on can be switched on once IT has configured it.

## Startup registry (ecosystem managers and platform admins)
- **Startups** lists every registered startup. Opening one is recorded in the audit log.
- **Private details** (revenue, funding, traction, contact) are shown only if the startup chose "our team and Hive staff".
- **Archive** hides a startup from its team. **Restore** brings it back.
- **Linked records:** add an ID from another system (for example an IMS application number) only after confirming it's the same company. An ID can be linked to one startup only.
- **Possible duplicates:**
  - **Scan for duplicates** compares registration numbers (strong evidence), websites (strong) and names (weak).
  - For each pair, write down your evidence, then choose **Different companies** or **Merge**, picking which record to keep.
  - A merge moves the team, founders and linked IDs to the kept record, and old links to the merged record keep working. Data-sharing choices are **not** moved; the kept startup must give its own.
  - Every merge can be undone from **Recent merges**.
- Never merge on a name alone. Confirm with the founders first.

## Growth assessments (rubric editors, approvers and reviewers)
- **Admin › Growth assessments** lists rubric versions. The starter rubric is a **draft proposal**: founders see nothing until a version is approved.
- **Editing:** open the draft, read the preview, and edit the JSON. Saving checks it and explains any problem, for example "bands must follow each other without gaps".
- **Approving:**
  - Someone other than the last editor must approve, with a note on what was reviewed.
  - Approving freezes the version and makes it the one used for new assessments.
  - To change it later, create a new draft version. Old results keep the version they used.
- **Submitted assessments:**
  - Reviewers can open a startup's result. The page checks that recalculating the result gives the same score.
  - To adjust a score or stage, write the reason. The founders see your reason next to the calculated result.

## Opportunities and events
- **Admin › Opportunities** lists open opportunities and has tabs for To review, Drafts, Closed, Rejected and Archived.
- **New opportunity:** fill in the details and who can apply. Leave a group empty if it's open to all. Tick **Let people register** for events or calls run through NSP, and set the number of places if it's limited.
- **To review:** these were shared by users. Check the link and details, then **Publish** or **Reject**. A rejection needs a reason, and the person who shared it sees that reason.
- **Registrations:** shows who registered, for which startup, and their message. You can confirm or decline each one and **Download CSV**. Downloads are recorded in the audit log.
- **Reminders:** people who saved an opportunity get one email 3 days before the deadline.

## Investors (ecosystem managers and platform admins)
- **Admin › Investors** lists investor applications. Start with **Waiting for review**.
- Check who the person is before you verify: their website or LinkedIn, fund registration, or a reference. A verified investor can see every startup that chose to be listed, so verify only people you would introduce yourself.
- **Verify** gives them the investor dashboard. **Do not verify** and **Suspend** need a note; the investor sees it. Suspending hides all startups and documents from them at once.
- Staff cannot open startups' data rooms from the admin area. Documents are shared only by founders, per investor, until a date they choose.

## Experts and mentors (ecosystem managers and platform admins)
- **Admin › Experts** lists expert applications. Check experience (website, LinkedIn or a reference) before you **Verify**. **Do not verify** and **Suspend** need a note the expert sees. Suspending stops them using their engagements.
- **Admin › Mentoring and engagements:** assign a verified mentor to a startup with a focus. The mentorship starts at once and both sides are emailed.
- The engagements list shows status, sessions held and feedback. Session notes are private to the startup and the expert and are not shown to staff.

## Vacancies (ecosystem managers and platform admins)
- Startups publish vacancies on the public **Jobs** board themselves. **Admin › Vacancies** lists them by status.
- **Take down** anything misleading, discriminatory, unlawful or unpaid where pay is required. A reason is required and the startup sees it. **Restore** puts it back as a draft.
- Staff cannot see applications or CVs. Candidate CV copies are deleted automatically after the retention period (`NSP_TALENT_CV_RETENTION_DAYS`).

## Marketplace and disputes (ecosystem managers and platform admins)
- **Admin › Marketplace and disputes** shows simple figures and the reports people sent.
- A report about a **conversation** lets you read that conversation; each time you open it is recorded in the audit log. You cannot read conversations nobody reported.
- **Resolve** or **Dismiss** with a note; the reporter is emailed the note. Tick **Take the listing down** when it breaks the rules; the startup sees your note. A removed listing can be **restored** as a draft.
- Hive does not take part in sales or hold money. Point people to the right authority for fraud.

## Announcements and email log (ecosystem managers and platform admins)
- **Admin › Announcements and email log:** write an announcement, choose the audience (everyone, startup team members, verified investors or verified experts) and save it as a draft. The audience size is shown next to each choice. **Send** delivers it once.
- Everyone in the audience sees it under **Notifications**. Only people who opted in to “News from Hive” get it by email, because announcements are newsletters.
- The **email delivery log** shows each email as waiting, sent or failed (after three tries), with the error. It does not keep message text or addresses. If many emails fail, check the SMTP settings in `.env`.

## KPIs and reports (ecosystem managers, auditors and platform admins)
- **Admin › KPIs and reports** shows ecosystem figures for the last 30 days, 90 days or 12 months. Each card says exactly what it counts; the full definitions are in `docs/kpi-definitions.md`.
- **Download KPIs (CSV)** saves the figures. **Download startup list (CSV)** (needs startup management rights) lists startups without contact details, financials or team members.
- Every download is recorded in the audit log. Figures are counts only; to see an individual startup, open it under **Startups**.

## Security emails
- People are emailed whenever two-factor sign-in is turned on, turned off, reset, or gets new recovery codes on their account. These emails cannot be switched off.
- When you create an admin account, ask the person to sign in and set up two-factor sign-in straight away, so nobody else can set it up with their password first.
