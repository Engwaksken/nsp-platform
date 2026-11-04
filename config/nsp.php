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
        'ai_assistant' => ['default' => false, 'label' => 'AI assistant', 'description' => 'Optional AI help with provenance and consent (Phase 6).'],
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

    'contact_consent_version' => '2026-11-04',
];
