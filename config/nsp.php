<?php

return [
    /*
     * Feature flags. These are defaults; an admin can override them in the
     * feature_flags table. Modules for later phases stay off until they are
     * built and approved.
     */
    'features' => [
        'oidc' => ['default' => (bool) env('NSP_OIDC_ENABLED', false), 'label' => 'Single sign-on (OIDC)', 'description' => 'Sign in through the shared Hive identity provider.'],
        'local_login' => ['default' => (bool) env('NSP_LOCAL_LOGIN', true), 'label' => 'Email and password login', 'description' => 'Local fallback login for development and admin until the shared IdP is live.'],
        'registry' => ['default' => (bool) env('NSP_REGISTRY_ENABLED', true), 'label' => 'Startup registry', 'description' => 'Startup profiles, teams, invitations and consent (Hive Startup Registry, hosted in NSP).'],
        'ims_integration' => ['default' => (bool) env('NSP_IMS_INTEGRATION', false), 'label' => 'IMS integration', 'description' => 'Connect flow, Registry API and events with Hive IMS; application and program summaries (docs/integration-contract-v1.md).'],
        'vgi' => ['default' => (bool) env('NSP_VGI_ENABLED', true), 'label' => 'Growth and VGI', 'description' => 'Growth assessments with an approved rubric, results and growth plans.'],
        'funding' => ['default' => (bool) env('NSP_FUNDING_ENABLED', true), 'label' => 'Funding and investors', 'description' => 'Funding requests, verified investors, introductions and private data rooms.'],
        'experts' => ['default' => (bool) env('NSP_EXPERTS_ENABLED', true), 'label' => 'Experts and mentors', 'description' => 'Verified experts, consultation requests, mentor assignments, sessions, actions and feedback.'],
        'talent' => ['default' => (bool) env('NSP_TALENT_ENABLED', true), 'label' => 'Talent and jobs', 'description' => 'Vacancies, candidate profiles with consent, applications, shortlisting and interviews.'],
        'opportunities' => ['default' => (bool) env('NSP_OPPORTUNITIES_ENABLED', true), 'label' => 'Opportunities and events', 'description' => 'Grants, accelerators, competitions, events and calls with eligibility, saving, registration and recommendations.'],
        'marketplace' => ['default' => (bool) env('NSP_MARKETPLACE_ENABLED', true), 'label' => 'Marketplace and showcase', 'description' => 'Public startup directory (with consent), offers, requests to buy, partnerships, inquiry messages and disputes.'],
        // Owner-alignment modules (docs/owner-alignment). Methodology content stays provisional until the owner approves it.
        'organizations' => ['default' => (bool) env('NSP_ORGANIZATIONS_ENABLED', true), 'label' => 'Organizations and portfolios', 'description' => 'Universities, ESOs, investors, development partners and corporates; memberships and startup-approved portfolio access.'],
        'venture_builder' => ['default' => (bool) env('NSP_VENTURE_BUILDER_ENABLED', true), 'label' => 'Venture Builder', 'description' => 'Six modules and 24 steps with evidence, review, documents and progress (curriculum is provisional until the owner approves it).'],
        'intelligence' => ['default' => (bool) env('NSP_INTELLIGENCE_ENABLED', true), 'label' => 'Venture Intelligence', 'description' => 'Rules-first guidance from VGI and Builder gaps, feedback and versioned planning documents. External AI stays behind the AI assistant flag.'],
        'challenges' => ['default' => (bool) env('NSP_CHALLENGES_ENABLED', true), 'label' => 'Challenge Exchange', 'description' => 'Moderated innovation and market challenges with evidence, review, versions and solution proposals.'],
        'knowledge' => ['default' => (bool) env('NSP_KNOWLEDGE_ENABLED', true), 'label' => 'Knowledge Hub', 'description' => 'Research, policy briefs, templates and toolkits with review, search, bookmarks and download tracking.'],
        // Locked until the investor controller, pages and tests exist (backlog CAP-01).
        'capital_exchange' => ['default' => false, 'label' => 'Capital Exchange', 'description' => 'Founder and investor capital pages: sharing scopes, due diligence, document requests, readiness report, pipeline and watchlist. Not built yet (CAP-01).'],
        'ai_assistant' => ['default' => (bool) env('NSP_AI_ASSISTANT_ENABLED', false), 'label' => 'AI assistant', 'description' => 'Optional writing help for founders (opt-in, labelled, rate-limited). Can be switched on only with an API key and recorded legal approval.'],
    ],

    'oidc' => [
        'issuer' => env('OIDC_ISSUER'),
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'redirect_uri' => env('OIDC_REDIRECT_URI'),
        'post_logout_redirect_uri' => env('OIDC_POST_LOGOUT_REDIRECT_URI'),
        'scopes' => env('OIDC_SCOPES', 'openid profile email'),
        // Never link an IdP identity to an existing local account by email unless the owner approves it.
        'link_verified_email' => (bool) env('OIDC_LINK_VERIFIED_EMAIL', false),
        'leeway_seconds' => 60,
        'cache_seconds' => 3600,
    ],

    'mfa' => [
        // Admin roles must use two-factor sign-in (authenticator app). Only switch off for local development.
        'enforce' => (bool) env('NSP_MFA_ENFORCE', true),
    ],

    // Link to Hive IMS shown to founders (optional).
    'ims_url' => env('IMS_URL'),

    'registry' => [
        'invite_days' => 7,
        // Bump when the consent wording below changes; each consent records the version it was given under.
        'consent_policy_version' => '2026-11-05',
        // Owner decision D4: wording needs legal review before launch (docs/decisions.md).
        'consent_purposes' => [
            'ims_prefill' => [
                'recipient' => 'ims',
                'label' => 'Prefill Hive program applications',
                'text' => 'Share our core profile (names, registration number, country, year founded, description, website, sector, location and team size) with Hive IMS so our program applications are prefilled. Financial figures are not shared.',
                'scope' => ['legal_name', 'display_name', 'registration_number', 'country', 'year_founded', 'description', 'website', 'sector', 'headquarters_location', 'team_size'],
            ],
            'nsp_directory' => [
                'recipient' => 'nsp',
                'label' => 'List us in the NSP startup directory',
                'text' => 'Show our display name, sector, location, description and website in the public NSP startup directory.',
                'scope' => ['display_name', 'sector', 'headquarters_location', 'description', 'website'],
            ],
            'investor_listing' => [
                'recipient' => 'investors',
                'label' => 'Show our funding request to verified investors',
                'text' => 'While we have an open funding request, Hive-verified investors on NSP may see our display name, sector, location, growth stage and the funding request we wrote for investors. Nothing else is shown, and documents are only shared with investors we choose.',
                'scope' => ['display_name', 'sector', 'headquarters_location', 'stage', 'funding_request'],
            ],
            'ecosystem_reports' => [
                'recipient' => 'hive',
                'label' => 'Include us in anonymised ecosystem reports',
                'text' => 'Let Hive include our sector, team size and revenue band in anonymised, combined ecosystem statistics. We are never named.',
                'scope' => ['sector', 'team_size', 'revenue_band'],
            ],
        ],
    ],

    // Version of the consent wording shown on the contact form; bump when the text changes.
    'experts' => [
        // Expertise areas experts choose from and founders filter by (spec: expert_skills).
        'areas' => [
            'strategy' => 'Business model and strategy', 'finance' => 'Finance and bookkeeping', 'fundraising' => 'Fundraising and investor readiness',
            'marketing' => 'Marketing and sales', 'product' => 'Product and technology', 'operations' => 'Operations and supply chain',
            'legal' => 'Legal, tax and compliance', 'people' => 'Team, HR and leadership', 'export' => 'Export and market access',
            'impact' => 'Impact measurement', 'agronomy' => 'Agronomy and value chains', 'pitching' => 'Pitching and storytelling',
        ],
        // Ratings are shown in the directory only once an expert has this many, so no single review can be identified.
        'min_reviews_shown' => 3,
    ],

    'talent' => [
        // Copies of CVs sent with applications are deleted this many days after the application or vacancy closes.
        'cv_retention_days' => (int) env('NSP_TALENT_CV_RETENTION_DAYS', 180),
        // Bump when the wording of the candidate search consent changes.
        'consent_version' => '2026-11-10',
        'cv_max_kb' => 5120,
    ],

    'marketplace' => [
        'categories' => [
            'software' => 'Software and digital services', 'agri' => 'Agricultural produce and inputs', 'manufactured' => 'Manufactured goods',
            'food' => 'Food and beverages', 'energy' => 'Energy and clean tech', 'logistics' => 'Logistics and transport',
            'marketing' => 'Marketing and creative', 'professional' => 'Professional services', 'training' => 'Training and education',
            'health' => 'Health products and services', 'finance' => 'Financial services', 'other' => 'Other',
        ],
    ],

    'notifications' => [
        // Activity notices people can switch per channel. Security and account emails are not listed:
        // they are always sent. Announcements are newsletters, so email is off until people opt in.
        'types' => [
            'registry' => ['label' => 'My startup team', 'description' => 'Requests to join your startup.', 'email' => true, 'in_app' => true],
            'opportunities' => ['label' => 'Saved opportunities', 'description' => 'Reminders before deadlines.', 'email' => true, 'in_app' => true],
            'funding' => ['label' => 'Funding and investors', 'description' => 'Introductions and data-room access.', 'email' => true, 'in_app' => true],
            'experts' => ['label' => 'Experts and mentors', 'description' => 'Requests, sessions and feedback.', 'email' => true, 'in_app' => true],
            'talent' => ['label' => 'Jobs and hiring', 'description' => 'Applications, interviews and invitations.', 'email' => true, 'in_app' => true],
            'marketplace' => ['label' => 'Marketplace messages', 'description' => 'New messages and report outcomes.', 'email' => true, 'in_app' => true],
            'announcements' => ['label' => 'News from Hive', 'description' => 'Announcements and newsletters from the Hive team.', 'email' => false, 'in_app' => true],
        ],
        // Read notifications are deleted after this many days, unread ones after a year; the delivery log after 180 days.
        'keep_read_days' => 90,
        'keep_unread_days' => 365,
        'keep_delivery_log_days' => 180,
    ],

    'ai' => [
        // Never commit the key. The assistant stays locked until legal review is recorded (backlog AI-605).
        'api_key' => env('ANTHROPIC_API_KEY'),
        'legal_approved' => (bool) env('NSP_AI_LEGAL_APPROVED', false),
        'model' => env('NSP_AI_MODEL', 'claude-opus-5-5'),
        'base_url' => env('ANTHROPIC_BASE_URL'),
        'effort' => env('NSP_AI_EFFORT', 'medium'),
        'max_tokens' => 4000,
        'timeout' => 60,
        'max_retries' => 2,
        'max_input_chars' => 4000,
        'consent_version' => '2026-11-14',
        'daily_limits' => [
            'user' => (int) env('NSP_AI_DAILY_PER_USER', 20),
            'startup' => (int) env('NSP_AI_DAILY_PER_STARTUP', 50),
            'platform' => (int) env('NSP_AI_DAILY_TOTAL', 500),
        ],
    ],

    'backup' => [
        // Outside storage/app/private and public so a backup never contains older backups.
        'path' => env('NSP_BACKUP_PATH', storage_path('app/backups')),
        'password' => env('NSP_BACKUP_PASSWORD'),
        'keep' => (int) env('NSP_BACKUP_KEEP', 14),
        'mysqldump' => env('NSP_MYSQLDUMP', 'mysqldump'),
        'connection' => null, // the default database connection
    ],

    // Bearer token for GET /health (uptime monitors). Leave empty to switch the endpoint off.
    'health_token' => env('NSP_HEALTH_TOKEN'),

    'contact_consent_version' => '2026-11-04',
];
